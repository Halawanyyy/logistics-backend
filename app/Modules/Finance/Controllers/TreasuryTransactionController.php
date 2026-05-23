<?php

namespace App\Modules\Finance\Controllers;

use App\Modules\Finance\Enums\MerchantWalletDirection;
use App\Modules\Finance\Models\TreasuryTransaction;
use App\Modules\Finance\Resources\TreasuryTransactionResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class TreasuryTransactionController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'treasury_account_id' => [
                'nullable',
                Rule::exists('treasury_accounts', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'direction' => ['nullable', Rule::in(MerchantWalletDirection::values())],
            'source_type' => ['nullable', 'string', 'max:255'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $transactions = TreasuryTransaction::query()
            ->with(['treasuryAccount', 'createdBy'])
            ->when(isset($validated['treasury_account_id']), fn ($query) => $query->where('treasury_account_id', $validated['treasury_account_id']))
            ->when(isset($validated['direction']), fn ($query) => $query->where('direction', $validated['direction']))
            ->when(isset($validated['source_type']), fn ($query) => $query->where('source_type', $validated['source_type']))
            ->when(isset($validated['date_from']), fn ($query) => $query->whereDate('created_at', '>=', $validated['date_from']))
            ->when(isset($validated['date_to']), fn ($query) => $query->whereDate('created_at', '<=', $validated['date_to']))
            ->latest()
            ->get();

        return TreasuryTransactionResource::collection($transactions);
    }
}
