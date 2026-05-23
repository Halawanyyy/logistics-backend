<?php

namespace App\Modules\Finance\Enums;

class MerchantWalletTransactionType
{
    public const COD_COLLECTED = 'cod_collected';
    public const SHIPPING_FEE = 'shipping_fee';
    public const RETURN_FEE = 'return_fee';
    public const SWAP_FEE = 'swap_fee';
    public const STORAGE_FEE = 'storage_fee';
    public const PICK_FEE = 'pick_fee';
    public const PACKING_FEE = 'packing_fee';
    public const WAREHOUSE_CHARGE = 'warehouse_charge';
    public const PAYOUT = 'payout';
    public const MANUAL_DEPOSIT = 'manual_deposit';
    public const MANUAL_WITHDRAW = 'manual_withdraw';
    public const ADJUSTMENT = 'adjustment';
    public const REFUND = 'refund';
    public const PENALTY = 'penalty';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::COD_COLLECTED,
            self::SHIPPING_FEE,
            self::RETURN_FEE,
            self::SWAP_FEE,
            self::STORAGE_FEE,
            self::PICK_FEE,
            self::PACKING_FEE,
            self::WAREHOUSE_CHARGE,
            self::PAYOUT,
            self::MANUAL_DEPOSIT,
            self::MANUAL_WITHDRAW,
            self::ADJUSTMENT,
            self::REFUND,
            self::PENALTY,
        ];
    }
}
