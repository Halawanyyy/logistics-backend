<?php

namespace App\Modules\Finance\Controllers;

use App\Modules\Audit\Enums\AuditEventType;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\Finance\Enums\ExpenseStatus;
use App\Modules\Finance\Models\Expense;
use App\Modules\Finance\Requests\ListExpensesRequest;
use App\Modules\Finance\Requests\StoreExpenseRequest;
use App\Modules\Finance\Requests\UpdateExpenseRequest;
use App\Modules\Finance\Resources\ExpenseResource;
use App\Modules\Finance\Services\TreasuryService;
use App\Modules\Settings\Services\SystemSettingService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class ExpenseController
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly SystemSettingService $systemSettingService,
        private readonly TreasuryService $treasuryService,
    ) {
    }

    public function index(ListExpensesRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();

        $expenses = Expense::query()
            ->with($this->relations())
            ->when(isset($validated['category']), fn ($query) => $query->where('category', $validated['category']))
            ->when(isset($validated['status']), fn ($query) => $query->where('status', $validated['status']))
            ->when(isset($validated['payment_method']), fn ($query) => $query->where('payment_method', $validated['payment_method']))
            ->when(isset($validated['date_from']), fn ($query) => $query->whereDate('expense_date', '>=', $validated['date_from']))
            ->when(isset($validated['date_to']), fn ($query) => $query->whereDate('expense_date', '<=', $validated['date_to']))
            ->latest()
            ->get();

        return ExpenseResource::collection($expenses);
    }

    public function store(StoreExpenseRequest $request): JsonResponse
    {
        $expense = Expense::create(
            $this->preparePayload([
                ...$request->validated(),
                'created_by' => $request->user()?->id,
            ], null, $request->user()?->id)
        );

        $this->treasuryService->syncExpense($expense, $request->user()?->id);

        return (new ExpenseResource($expense->load($this->relations())))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Expense $expense): ExpenseResource
    {
        return new ExpenseResource($expense->load($this->relations()));
    }

    public function update(UpdateExpenseRequest $request, Expense $expense): ExpenseResource
    {
        if (in_array($expense->status, [ExpenseStatus::PAID, ExpenseStatus::REJECTED, ExpenseStatus::CANCELLED], true)) {
            $this->fail('Finalized expenses cannot be updated.');
        }

        $expense->update($this->preparePayload($request->validated(), $expense, $request->user()?->id));
        $this->treasuryService->syncExpense($expense->fresh(), $request->user()?->id);

        return new ExpenseResource($expense->fresh()->load($this->relations()));
    }

    public function approve(Request $request, Expense $expense): ExpenseResource
    {
        if ($expense->status !== ExpenseStatus::PENDING) {
            $this->fail('Only pending expenses can be approved.');
        }

        $oldStatus = $expense->status;
        $expense->update([
            'status' => ExpenseStatus::APPROVED,
            'approved_by' => $request->user()?->id,
            'approved_at' => now(),
        ]);

        $this->auditLogService->logStatusChange(
            $expense->fresh(),
            AuditEventType::EXPENSE_APPROVED,
            $oldStatus,
            ExpenseStatus::APPROVED,
            [
                'user' => $request->user(),
            ],
            'Expense approved.',
            null,
            'approved',
        );

        return new ExpenseResource($expense->fresh()->load($this->relations()));
    }

    public function reject(Request $request, Expense $expense): ExpenseResource
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string'],
        ]);

        if (! in_array($expense->status, [ExpenseStatus::PENDING, ExpenseStatus::APPROVED], true)) {
            $this->fail('Only pending or approved expenses can be rejected.');
        }

        $oldStatus = $expense->status;
        $expense->update([
            'status' => ExpenseStatus::REJECTED,
            'rejected_by' => $request->user()?->id,
            'rejected_at' => now(),
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        $this->auditLogService->logStatusChange(
            $expense->fresh(),
            AuditEventType::EXPENSE_REJECTED,
            $oldStatus,
            ExpenseStatus::REJECTED,
            [
                'user' => $request->user(),
                'metadata' => [
                    'rejection_reason' => $validated['rejection_reason'],
                ],
            ],
            'Expense rejected.',
            null,
            'rejected',
        );

        return new ExpenseResource($expense->fresh()->load($this->relations()));
    }

    public function markPaid(Request $request, Expense $expense): ExpenseResource
    {
        $validated = $request->validate([
            'treasury_account_id' => [
                'nullable',
                Rule::exists('treasury_accounts', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($expense->status !== ExpenseStatus::APPROVED) {
            $this->fail('Only approved expenses can be marked paid.');
        }

        $oldStatus = $expense->status;
        $expense->update([
            'status' => ExpenseStatus::PAID,
            'treasury_account_id' => $validated['treasury_account_id'] ?? $expense->treasury_account_id,
            'paid_by' => $request->user()?->id,
            'paid_at' => $validated['paid_at'] ?? now(),
            'notes' => $validated['notes'] ?? $expense->notes,
        ]);

        $this->treasuryService->syncExpense($expense->fresh(), $request->user()?->id);

        $this->auditLogService->logStatusChange(
            $expense->fresh(),
            AuditEventType::EXPENSE_PAID,
            $oldStatus,
            ExpenseStatus::PAID,
            [
                'user' => $request->user(),
                'metadata' => [
                    'treasury_account_id' => $expense->treasury_account_id,
                ],
            ],
            'Expense marked as paid.',
            null,
            'paid',
        );

        return new ExpenseResource($expense->fresh()->load($this->relations()));
    }

    public function destroy(Expense $expense): JsonResponse
    {
        if (in_array($expense->status, [ExpenseStatus::APPROVED, ExpenseStatus::PAID], true)) {
            $this->fail('Approved or paid expenses cannot be deleted.');
        }

        $expense->delete();

        return response()->json([
            'message' => 'Expense deleted successfully',
        ]);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function preparePayload(array $data, ?Expense $expense, ?int $userId): array
    {
        $status = $data['status'] ?? $expense?->status ?? ExpenseStatus::PENDING;
        $hasExplicitStatus = array_key_exists('status', $data);
        $approvalLimit = (float) $this->systemSettingService->get('expense_approval_limit', 0);

        if (
            ! $hasExplicitStatus
            && $expense?->approved_at === null
            && $status === ExpenseStatus::PENDING
            && $approvalLimit > 0
            && (float) ($data['amount'] ?? $expense?->amount ?? 0) <= $approvalLimit
        ) {
            $status = ExpenseStatus::APPROVED;
            $data['status'] = $status;
        }

        if ($status === ExpenseStatus::APPROVED && $expense?->approved_at === null) {
            $data['approved_by'] = $data['approved_by'] ?? $userId;
            $data['approved_at'] = $data['approved_at'] ?? now();
        }

        if ($status === ExpenseStatus::PAID) {
            if ($expense?->approved_at === null) {
                $data['approved_by'] = $data['approved_by'] ?? $userId;
                $data['approved_at'] = $data['approved_at'] ?? now();
            }

            $data['paid_by'] = $data['paid_by'] ?? $userId;
            $data['paid_at'] = $data['paid_at'] ?? now();
        }

        if ($status === ExpenseStatus::REJECTED) {
            $data['rejected_by'] = $data['rejected_by'] ?? $userId;
            $data['rejected_at'] = $data['rejected_at'] ?? now();
        }

        return $data;
    }

    /**
     * @return list<string>
     */
    private function relations(): array
    {
        return [
            'treasuryAccount',
            'createdBy',
            'approvedBy',
            'paidBy',
            'rejectedBy',
        ];
    }

    private function fail(string $message): never
    {
        throw new HttpResponseException(response()->json([
            'message' => $message,
        ], 422));
    }
}
