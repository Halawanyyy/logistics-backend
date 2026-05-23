<?php

namespace App\Modules\Finance\Models;

use App\Models\Merchant;
use App\Models\User;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MerchantPayout extends Model
{
    use Auditable, SoftDeletes;

    /**
     * @var array<string, string>
     */
    protected array $auditEventTypes = [
        'created' => 'payout_created',
        'updated' => 'payout_updated',
        'deleted' => 'payout_deleted',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'merchant_id',
        'treasury_account_id',
        'amount',
        'status',
        'payment_method',
        'reference_number',
        'notes',
        'created_by',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'paid_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'paid_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function treasuryAccount(): BelongsTo
    {
        return $this->belongsTo(TreasuryAccount::class);
    }

    public function walletTransaction(): HasOne
    {
        return $this->hasOne(MerchantWalletTransaction::class, 'source_id')
            ->where('source_type', 'merchant_payout')
            ->where('transaction_type', \App\Modules\Finance\Enums\MerchantWalletTransactionType::PAYOUT);
    }
}
