<?php

namespace App\Modules\Finance\Controllers;

use App\Modules\Finance\Enums\TreasuryAccountStatus;
use App\Modules\Finance\Enums\TreasuryAccountType;
use App\Modules\Finance\Models\TreasuryAccount;
use App\Modules\Finance\Resources\TreasuryAccountResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class TreasuryAccountController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'type' => ['nullable', Rule::in(TreasuryAccountType::values())],
            'status' => ['nullable', Rule::in(TreasuryAccountStatus::values())],
        ]);

        $accounts = TreasuryAccount::query()
            ->when(isset($validated['type']), fn ($query) => $query->where('type', $validated['type']))
            ->when(isset($validated['status']), fn ($query) => $query->where('status', $validated['status']))
            ->latest()
            ->get();

        return TreasuryAccountResource::collection($accounts);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255', Rule::unique('treasury_accounts', 'code')],
            'type' => ['required', Rule::in(TreasuryAccountType::values())],
            'opening_balance' => ['nullable', 'numeric'],
            'current_balance' => ['nullable', 'numeric'],
            'status' => ['nullable', Rule::in(TreasuryAccountStatus::values())],
        ]);

        $openingBalance = (float) ($validated['opening_balance'] ?? 0);

        $account = TreasuryAccount::create([
            'name' => $validated['name'],
            'code' => $validated['code'],
            'type' => $validated['type'],
            'opening_balance' => number_format($openingBalance, 2, '.', ''),
            'current_balance' => number_format((float) ($validated['current_balance'] ?? $openingBalance), 2, '.', ''),
            'status' => $validated['status'] ?? TreasuryAccountStatus::ACTIVE,
        ]);

        return (new TreasuryAccountResource($account))
            ->response()
            ->setStatusCode(201);
    }

    public function show(TreasuryAccount $treasuryAccount): TreasuryAccountResource
    {
        return new TreasuryAccountResource($treasuryAccount);
    }

    public function update(Request $request, TreasuryAccount $treasuryAccount): TreasuryAccountResource
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:255', Rule::unique('treasury_accounts', 'code')->ignore($treasuryAccount->id)],
            'type' => ['sometimes', Rule::in(TreasuryAccountType::values())],
            'opening_balance' => ['sometimes', 'numeric'],
            'status' => ['sometimes', Rule::in(TreasuryAccountStatus::values())],
        ]);

        $treasuryAccount->update($validated);

        return new TreasuryAccountResource($treasuryAccount->fresh());
    }

    public function destroy(TreasuryAccount $treasuryAccount): JsonResponse
    {
        if ($treasuryAccount->transactions()->exists()) {
            return response()->json([
                'message' => 'Treasury account with transactions cannot be deleted.',
            ], 422);
        }

        $treasuryAccount->delete();

        return response()->json([
            'message' => 'Treasury account deleted successfully',
        ]);
    }
}
