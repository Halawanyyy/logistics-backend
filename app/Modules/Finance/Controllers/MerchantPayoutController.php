<?php

namespace App\Modules\Finance\Controllers;

use App\Models\Merchant;
use App\Modules\Audit\Enums\AuditEventType;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\Finance\Enums\PaymentMethod;
use App\Modules\Finance\Enums\PayoutStatus;
use App\Modules\Finance\Models\MerchantPayout;
use App\Modules\Finance\Requests\ListMerchantPayoutsRequest;
use App\Modules\Finance\Requests\StoreMerchantPayoutRequest;
use App\Modules\Finance\Requests\UpdateMerchantPayoutRequest;
use App\Modules\Finance\Resources\MerchantPayoutResource;
use App\Modules\Finance\Services\FinanceSummaryService;
use App\Modules\Finance\Services\MerchantWalletService;
use App\Modules\Finance\Services\TreasuryService;
use App\Modules\Settings\Services\SystemSettingService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MerchantPayoutController
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly FinanceSummaryService $financeSummaryService,
        private readonly MerchantWalletService $merchantWalletService,
        private readonly SystemSettingService $systemSettingService,
        private readonly TreasuryService $treasuryService,
    ) {
    }

    public function index(ListMerchantPayoutsRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();

        $payouts = MerchantPayout::query()
            ->with($this->relations())
            ->when(isset($validated['merchant_id']), fn ($query) => $query->where('merchant_id', $validated['merchant_id']))
            ->when(isset($validated['status']), fn ($query) => $query->where('status', $validated['status']))
            ->when(isset($validated['payment_method']), fn ($query) => $query->where('payment_method', $validated['payment_method']))
            ->when(isset($validated['date_from']), fn ($query) => $query->whereDate('created_at', '>=', $validated['date_from']))
            ->when(isset($validated['date_to']), fn ($query) => $query->whereDate('created_at', '<=', $validated['date_to']))
            ->latest()
            ->get();

        return MerchantPayoutResource::collection($payouts);
    }

    public function store(StoreMerchantPayoutRequest $request): JsonResponse
    {
        $data = $this->preparePayload($request->validated(), null, $request->user()?->id);
        $merchant = Merchant::query()->findOrFail($data['merchant_id']);
        $this->ensurePayoutWithinAvailableBalance($merchant, $data);

        $payout = DB::transaction(function () use ($data, $request) {
            $payout = MerchantPayout::create([
                ...$data,
                'created_by' => $request->user()?->id,
            ]);

            $this->syncCompletedPayout($payout, $request->user()?->id);

            return $payout;
        });

        return (new MerchantPayoutResource($payout->load($this->relations())))
            ->response()
            ->setStatusCode(201);
    }

    public function show(MerchantPayout $merchantPayout): MerchantPayoutResource
    {
        return new MerchantPayoutResource($merchantPayout->load($this->relations()));
    }

    public function update(UpdateMerchantPayoutRequest $request, MerchantPayout $merchantPayout): MerchantPayoutResource
    {
        if (in_array($merchantPayout->status, [PayoutStatus::COMPLETED, PayoutStatus::REJECTED, PayoutStatus::CANCELLED], true)) {
            $this->fail('Finalized payouts cannot be updated.');
        }

        $data = $this->preparePayload($request->validated(), $merchantPayout, $request->user()?->id);
        $this->ensurePayoutWithinAvailableBalance($merchantPayout->merchant, $data, $merchantPayout);

        DB::transaction(function () use ($merchantPayout, $data, $request) {
            $merchantPayout->update($data);
            $this->syncCompletedPayout($merchantPayout->fresh(), $request->user()?->id);
        });

        return new MerchantPayoutResource($merchantPayout->fresh()->load($this->relations()));
    }

    public function approve(Request $request, MerchantPayout $merchantPayout): MerchantPayoutResource
    {
        if ($merchantPayout->status !== PayoutStatus::PENDING) {
            $this->fail('Only pending payouts can be approved.');
        }

        $oldStatus = $merchantPayout->status;
        $merchantPayout->update([
            'status' => PayoutStatus::APPROVED,
            'approved_by' => $request->user()?->id,
            'approved_at' => now(),
        ]);

        $this->auditLogService->logStatusChange(
            $merchantPayout->fresh(),
            AuditEventType::PAYOUT_APPROVED,
            $oldStatus,
            PayoutStatus::APPROVED,
            [
                'user' => $request->user(),
            ],
            'Merchant payout approved.',
            null,
            'approved',
        );

        return new MerchantPayoutResource($merchantPayout->fresh()->load($this->relations()));
    }

    public function reject(Request $request, MerchantPayout $merchantPayout): MerchantPayoutResource
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string'],
        ]);

        if (! in_array($merchantPayout->status, [PayoutStatus::PENDING, PayoutStatus::APPROVED], true)) {
            $this->fail('Only pending or approved payouts can be rejected.');
        }

        $oldStatus = $merchantPayout->status;
        $merchantPayout->update([
            'status' => PayoutStatus::REJECTED,
            'rejected_by' => $request->user()?->id,
            'rejected_at' => now(),
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        $this->auditLogService->logStatusChange(
            $merchantPayout->fresh(),
            AuditEventType::PAYOUT_REJECTED,
            $oldStatus,
            PayoutStatus::REJECTED,
            [
                'user' => $request->user(),
                'metadata' => [
                    'rejection_reason' => $validated['rejection_reason'],
                ],
            ],
            'Merchant payout rejected.',
            null,
            'rejected',
        );

        return new MerchantPayoutResource($merchantPayout->fresh()->load($this->relations()));
    }

    public function complete(Request $request, MerchantPayout $merchantPayout): MerchantPayoutResource
    {
        $validated = $request->validate([
            'treasury_account_id' => [
                'nullable',
                Rule::exists('treasury_accounts', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'payment_method' => ['nullable', Rule::in(PaymentMethod::values())],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'paid_at' => ['nullable', 'date'],
        ]);

        if ($merchantPayout->status !== PayoutStatus::APPROVED) {
            $this->fail('Only approved payouts can be completed.');
        }

        $oldStatus = $merchantPayout->status;
        $this->ensurePayoutWithinAvailableBalance($merchantPayout->merchant, [
            'amount' => $merchantPayout->amount,
            'status' => PayoutStatus::COMPLETED,
        ], $merchantPayout);

        DB::transaction(function () use ($merchantPayout, $validated, $request) {
            $merchantPayout->update([
                'status' => PayoutStatus::COMPLETED,
                'treasury_account_id' => $validated['treasury_account_id'] ?? $merchantPayout->treasury_account_id,
                'payment_method' => $validated['payment_method'] ?? $merchantPayout->payment_method,
                'reference_number' => $validated['reference_number'] ?? $merchantPayout->reference_number,
                'notes' => $validated['notes'] ?? $merchantPayout->notes,
                'paid_at' => $validated['paid_at'] ?? now(),
            ]);

            $this->syncCompletedPayout($merchantPayout->fresh(), $request->user()?->id);
        });

        $this->auditLogService->logStatusChange(
            $merchantPayout->fresh(),
            AuditEventType::PAYOUT_COMPLETED,
            $oldStatus,
            PayoutStatus::COMPLETED,
            [
                'user' => $request->user(),
                'metadata' => [
                    'treasury_account_id' => $merchantPayout->treasury_account_id,
                ],
            ],
            'Merchant payout completed.',
            null,
            'completed',
        );

        return new MerchantPayoutResource($merchantPayout->fresh()->load($this->relations()));
    }

    public function destroy(MerchantPayout $merchantPayout): JsonResponse
    {
        if (in_array($merchantPayout->status, [PayoutStatus::APPROVED, PayoutStatus::COMPLETED], true)) {
            $this->fail('Approved or completed payouts cannot be deleted.');
        }

        $merchantPayout->delete();

        return response()->json([
            'message' => 'Merchant payout deleted successfully',
        ]);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function preparePayload(array $data, ?MerchantPayout $merchantPayout = null, ?int $userId = null): array
    {
        $status = $data['status'] ?? $merchantPayout?->status ?? PayoutStatus::PENDING;
        $hasPaidAt = array_key_exists('paid_at', $data);
        $hasExplicitStatus = array_key_exists('status', $data);
        $approvalLimit = (float) $this->systemSettingService->get('payout_approval_limit', 0);

        if (
            ! $hasExplicitStatus
            && $merchantPayout?->approved_at === null
            && $status === PayoutStatus::PENDING
            && $approvalLimit > 0
            && (float) ($data['amount'] ?? $merchantPayout?->amount ?? 0) <= $approvalLimit
        ) {
            $status = PayoutStatus::APPROVED;
            $data['status'] = $status;
        }

        if ($status === PayoutStatus::APPROVED && $merchantPayout?->approved_at === null) {
            $data['approved_by'] = $data['approved_by'] ?? $userId;
            $data['approved_at'] = $data['approved_at'] ?? now();
        }

        if ($status === PayoutStatus::REJECTED) {
            $data['rejected_by'] = $data['rejected_by'] ?? $userId;
            $data['rejected_at'] = $data['rejected_at'] ?? now();
        }

        if ($status === PayoutStatus::COMPLETED) {
            if (! $hasPaidAt) {
                $data['paid_at'] = now();
            }

            if ($merchantPayout?->approved_at === null) {
                $data['approved_by'] = $data['approved_by'] ?? $userId;
                $data['approved_at'] = $data['approved_at'] ?? now();
            }
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function ensurePayoutWithinAvailableBalance(Merchant $merchant, array $data, ?MerchantPayout $merchantPayout = null): void
    {
        $status = $data['status'] ?? $merchantPayout?->status ?? PayoutStatus::PENDING;

        if (in_array($status, [PayoutStatus::REJECTED, PayoutStatus::CANCELLED], true)) {
            return;
        }

        $amount = (float) ($data['amount'] ?? $merchantPayout?->amount ?? 0);
        $availableBalance = $this->financeSummaryService->getMerchantAvailableBalance($merchant, $merchantPayout);

        if ($amount > $availableBalance) {
            $this->fail('Payout amount exceeds merchant available balance.');
        }
    }

    private function syncCompletedPayout(MerchantPayout $merchantPayout, ?int $userId): void
    {
        if ($merchantPayout->status !== PayoutStatus::COMPLETED) {
            return;
        }

        $this->merchantWalletService->syncPayoutTransaction($merchantPayout, $userId);
        $this->treasuryService->syncMerchantPayout($merchantPayout, $userId);
    }

    /**
     * @return list<string>
     */
    private function relations(): array
    {
        return [
            'merchant',
            'treasuryAccount',
            'createdBy',
            'approvedBy',
            'rejectedBy',
            'walletTransaction',
        ];
    }

    private function fail(string $message): never
    {
        throw new HttpResponseException(response()->json([
            'message' => $message,
        ], 422));
    }
}
