# Merchant Portal Phase 3B Finance Completion Report

## Summary

Merchant Portal Phase 3B adds merchant-facing finance visibility on top of the existing Finance, Wallet, Invoice, Payout, Warehouse Charges, and Merchant Portal foundations.

Implemented in this phase:

- merchant finance summary
- merchant wallet summary
- merchant wallet transaction list and detail
- merchant invoice list and detail
- merchant invoice preview and download
- merchant payout list and detail
- merchant payout request creation
- warehouse charges continue to reuse the existing Phase 3A warehouse endpoint and are reflected in finance totals without duplicating a finance alias route

## New APIs

Merchant portal finance endpoints:

- `GET /api/merchant-portal/finance/summary`
- `GET /api/merchant-portal/finance/wallet/summary`
- `GET /api/merchant-portal/finance/wallet/transactions`
- `GET /api/merchant-portal/finance/wallet/transactions/{transaction}`
- `GET /api/merchant-portal/finance/invoices`
- `GET /api/merchant-portal/finance/invoices/{invoice}`
- `GET /api/merchant-portal/finance/invoices/{invoice}/preview`
- `GET /api/merchant-portal/finance/invoices/{invoice}/download`
- `GET /api/merchant-portal/finance/payouts`
- `GET /api/merchant-portal/finance/payouts/{payout}`
- `POST /api/merchant-portal/finance/payouts/request`

## Security And Scoping Rules

- all merchant finance endpoints require `auth:sanctum` and `merchant.portal`
- merchant context comes only from the authenticated merchant portal membership
- `merchant_id` is rejected with `422` on merchant portal finance requests
- cross-merchant resource access returns `404` through merchant-scoped queries
- capability checks are enforced through `MerchantPortalAccessService`
- finance responses are merchant-safe and omit internal reconciliation-only fields

Merchants cannot:

- create, approve, or reject wallet transactions
- create, update, or delete invoices
- approve, reject, complete, or delete payouts
- view company profit summaries
- view reconciliation-only internal finance data

## Merchant Finance Summary Flow

1. merchant opens finance summary
2. summary is derived only for the current authenticated merchant
3. summary combines existing finance totals, wallet balances, payout summaries, and invoice counts
4. internal-only profit and reconciliation fields remain hidden

## Merchant Wallet Flow

1. merchant reads wallet summary for the current merchant
2. merchant lists wallet transactions with safe filters
3. merchant can inspect one wallet transaction in detail
4. wallet remains fully read-only in the portal

## Merchant Invoice Flow

1. merchant lists only its own invoices
2. merchant can inspect one invoice with safe totals and item lines
3. merchant can preview its own invoice
4. merchant can download its own invoice PDF through the existing invoice download flow

## Merchant Payout Request Flow

1. merchant lists its own payouts
2. merchant can inspect one payout
3. merchant can submit a payout request
4. payout request always starts as `pending`
5. payout request never auto-approves and never completes from the portal
6. finance approval and treasury execution remain internal-only

## What Merchants Can Do

- view finance summary
- view wallet summary
- view wallet transactions
- view invoices
- preview and download invoices
- view payouts
- request payouts

## What Remains Internal Or Admin Only

- wallet deposits and withdrawals
- wallet approval and rejection
- payout approval, rejection, and completion
- invoice creation, refresh, and deletion
- treasury movement
- company profit and reconciliation reporting

## Supported Filters

Finance summary:

- `date_from`
- `date_to`

Wallet transactions:

- `date_from`
- `date_to`
- `type`
- `direction`
- `status`
- `reference_type`
- `search`

Invoices:

- `date_from`
- `date_to`
- `status`
- `invoice_number`
- `search`

Payouts:

- `date_from`
- `date_to`
- `status`
- `payout_number`
- `search`

## Response Examples

Payout request response:

```json
{
  "data": {
    "id": 12,
    "payout_reference": null,
    "status": "pending",
    "amount": "250.00",
    "method": "bank_transfer",
    "notes": "Please process payout\nBank: Test Bank | Account Name: ABC Store | Account Number: 123456789",
    "requested_at": "2026-05-31T18:30:00.000000Z",
    "approved_at": null,
    "completed_at": null,
    "rejected_at": null,
    "rejection_reason": null,
    "created_at": "2026-05-31T18:30:00.000000Z"
  }
}
```

## Files Added

- `app/Modules/MerchantPortal/Controllers/MerchantPortalFinanceController.php`
- `app/Modules/MerchantPortal/Controllers/MerchantPortalWalletController.php`
- `app/Modules/MerchantPortal/Controllers/MerchantPortalInvoiceController.php`
- `app/Modules/MerchantPortal/Controllers/MerchantPortalPayoutController.php`
- `app/Modules/MerchantPortal/Requests/MerchantPortalFinanceScopedRequest.php`
- `app/Modules/MerchantPortal/Requests/MerchantPortalFinanceSummaryRequest.php`
- `app/Modules/MerchantPortal/Requests/ListMerchantPortalWalletTransactionsRequest.php`
- `app/Modules/MerchantPortal/Requests/ListMerchantPortalInvoicesRequest.php`
- `app/Modules/MerchantPortal/Requests/ListMerchantPortalPayoutsRequest.php`
- `app/Modules/MerchantPortal/Requests/StoreMerchantPortalPayoutRequest.php`
- `app/Modules/MerchantPortal/Resources/MerchantPortalFinanceSummaryResource.php`
- `app/Modules/MerchantPortal/Resources/MerchantPortalWalletSummaryResource.php`
- `app/Modules/MerchantPortal/Resources/MerchantPortalWalletTransactionResource.php`
- `app/Modules/MerchantPortal/Resources/MerchantPortalInvoiceResource.php`
- `app/Modules/MerchantPortal/Resources/MerchantPortalInvoiceDetailResource.php`
- `app/Modules/MerchantPortal/Resources/MerchantPortalPayoutResource.php`
- `app/Modules/MerchantPortal/Resources/MerchantPortalPayoutDetailResource.php`
- `app/Modules/MerchantPortal/Services/MerchantPortalFinanceService.php`
- `tests/Feature/MerchantPortalPhaseThreeFinanceTest.php`
- `docs/merchant-portal-phase-3b-finance-completion-report.md`

## Files Modified

- `app/Modules/MerchantPortal/Routes/api.php`
- `app/Modules/MerchantPortal/Services/MerchantPortalCapabilityService.php`
- `tests/Feature/Concerns/InteractsWithPlatform.php`
- `docs/api-documentation.md`
- `docs/api-endpoints-list.md`

## Tests Added

- `tests/Feature/MerchantPortalPhaseThreeFinanceTest.php`

Coverage focus:

- auth and active merchant membership
- merchant-only wallet, invoice, payout, and summary scoping
- `merchant_id` rejection
- invoice preview and download scoping
- payout request validation and safe pending-only creation
- capability enforcement for owner, admin, accountant, staff, and warehouse viewer roles
- internal finance API regression coverage

## Manual Commands To Run

```powershell
php artisan test --filter=MerchantPortalPhaseThreeFinanceTest
php artisan test --filter=MerchantPortalPhaseOneTest
php artisan test --filter=MerchantPortalPhaseTwoTest
php artisan test --filter=MerchantPortalPhaseThreeWarehouseTest
php artisan test --filter=FinanceWalletFlowTest
php artisan test
```

## Future Phases

- merchant finance exports
- merchant payout bank profile management
- merchant invoice notifications
- merchant warehouse-charge finance alias views if needed
