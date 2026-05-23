<?php

namespace App\Modules\Audit\Enums;

class AuditEventType
{
    public const ORDER_CREATED = 'order_created';
    public const ORDER_UPDATED = 'order_updated';
    public const ORDER_CONFIRMED = 'order_confirmed';
    public const ORDER_CANCELLED = 'order_cancelled';
    public const SHIPMENT_CREATED = 'shipment_created';
    public const SHIPMENT_UPDATED = 'shipment_updated';
    public const SHIPMENT_STATUS_UPDATED = 'shipment_status_updated';
    public const SHIPMENT_DRIVER_ASSIGNED = 'shipment_driver_assigned';
    public const DELIVERY_ATTEMPT_CREATED = 'delivery_attempt_created';
    public const PARTIAL_DELIVERY_COMPLETED = 'partial_delivery_completed';
    public const WALLET_TRANSACTION_CREATED = 'wallet_transaction_created';
    public const WALLET_TRANSACTION_APPROVED = 'wallet_transaction_approved';
    public const WALLET_TRANSACTION_REJECTED = 'wallet_transaction_rejected';
    public const PAYOUT_CREATED = 'payout_created';
    public const PAYOUT_APPROVED = 'payout_approved';
    public const PAYOUT_REJECTED = 'payout_rejected';
    public const PAYOUT_COMPLETED = 'payout_completed';
    public const EXPENSE_CREATED = 'expense_created';
    public const EXPENSE_APPROVED = 'expense_approved';
    public const EXPENSE_REJECTED = 'expense_rejected';
    public const EXPENSE_PAID = 'expense_paid';
    public const TREASURY_TRANSACTION_CREATED = 'treasury_transaction_created';
    public const INVENTORY_ADJUSTED = 'inventory_adjusted';
    public const STOCK_RESERVED = 'stock_reserved';
    public const STOCK_RELEASED = 'stock_released';
    public const WAREHOUSE_RETURN_CREATED = 'warehouse_return_created';
    public const PICKING_COMPLETED = 'picking_completed';
    public const PACKING_COMPLETED = 'packing_completed';
    public const MANIFEST_GENERATED = 'manifest_generated';
    public const MANIFEST_CLOSED = 'manifest_closed';
    public const ROLE_UPDATED = 'role_updated';
    public const ROLE_ASSIGNED = 'role_assigned';
    public const PERMISSION_UPDATED = 'permission_updated';
    public const SETTING_UPDATED = 'setting_updated';
    public const SETTINGS_BULK_UPDATED = 'settings_bulk_updated';
    public const EXPORT_GENERATED = 'export_generated';
    public const ATTACHMENT_UPLOADED = 'attachment_uploaded';
    public const ATTACHMENT_DELETED = 'attachment_deleted';
    public const MERCHANT_INTEGRATION_TOKEN_CREATED = 'merchant_integration_token_created';
    public const MERCHANT_INTEGRATION_TOKEN_REVOKED = 'merchant_integration_token_revoked';
    public const MERCHANT_INTEGRATION_TOKEN_DELETED = 'merchant_integration_token_deleted';
    public const INTEGRATION_TOKEN_USED = 'integration_token_used';
    public const INTEGRATION_ORDER_CREATED = 'integration_order_created';
    public const LOGIN = 'login';
    public const LOGIN_FAILED = 'login_failed';
    public const LOGOUT = 'logout';
}
