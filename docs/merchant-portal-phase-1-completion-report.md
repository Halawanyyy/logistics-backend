# Merchant Portal Phase 1 Completion Report

## Summary

Merchant Portal Phase 1 adds the secure merchant-facing API foundation:

- `merchant_users` table
- merchant portal roles and statuses
- merchant portal middleware and access service
- `/api/merchant-portal/me`
- `/api/merchant-portal/profile`
- internal admin merchant portal user management
- strict membership and primary-owner validation
- security-focused feature tests

## New APIs

- `GET /api/merchant-portal/me`
- `GET /api/merchant-portal/profile`
- `GET /api/merchants/{merchant}/portal-users`
- `POST /api/merchants/{merchant}/portal-users`
- `GET /api/merchants/{merchant}/portal-users/{merchantUser}`
- `PUT /api/merchants/{merchant}/portal-users/{merchantUser}`
- `DELETE /api/merchants/{merchant}/portal-users/{merchantUser}`
- `POST /api/merchants/{merchant}/portal-users/{merchantUser}/suspend`
- `POST /api/merchants/{merchant}/portal-users/{merchantUser}/activate`

## Merchant Portal Roles

- `owner`
- `admin`
- `staff`
- `accountant`
- `warehouse_viewer`

## Merchant Portal Statuses

- `invited`
- `active`
- `suspended`

## Security Rules

- merchant portal APIs require Sanctum auth
- merchant context comes only from authenticated membership
- request/query `merchant_id` is rejected on merchant portal routes
- invited, suspended, soft-deleted, and inactive-merchant memberships are denied
- internal users without membership are denied
- duplicate active/non-deleted memberships are blocked
- duplicate active primary owners are blocked

## Files Added

- `database/migrations/2026_05_31_000001_create_merchant_users_table.php`
- `app/Modules/MerchantPortal/Enums/MerchantPortalRole.php`
- `app/Modules/MerchantPortal/Enums/MerchantPortalUserStatus.php`
- `app/Modules/MerchantPortal/Models/MerchantUser.php`
- `app/Modules/MerchantPortal/Services/MerchantPortalCapabilityService.php`
- `app/Modules/MerchantPortal/Services/MerchantPortalAccessService.php`
- `app/Modules/MerchantPortal/Services/MerchantPortalUserManagementService.php`
- `app/Modules/MerchantPortal/Middleware/EnsureMerchantPortalUser.php`
- `app/Modules/MerchantPortal/Requests/ListMerchantPortalUsersRequest.php`
- `app/Modules/MerchantPortal/Requests/StoreMerchantPortalUserRequest.php`
- `app/Modules/MerchantPortal/Requests/UpdateMerchantPortalUserRequest.php`
- `app/Modules/MerchantPortal/Controllers/MerchantPortalMeController.php`
- `app/Modules/MerchantPortal/Controllers/MerchantPortalProfileController.php`
- `app/Modules/MerchantPortal/Controllers/MerchantPortalUserManagementController.php`
- `app/Modules/MerchantPortal/Resources/MerchantPortalUserResource.php`
- `app/Modules/MerchantPortal/Resources/MerchantPortalMeResource.php`
- `app/Modules/MerchantPortal/Resources/MerchantPortalProfileResource.php`
- `app/Modules/MerchantPortal/Routes/api.php`
- `tests/Feature/MerchantPortalPhaseOneTest.php`

## Files Modified

- `app/Models/User.php`
- `app/Models/Merchant.php`
- `app/Modules/RolePermission/Support/RolePermissionDefaults.php`
- `bootstrap/app.php`
- `routes/api.php`
- `tests/Feature/Concerns/InteractsWithPlatform.php`
- `docs/api-documentation.md`
- `docs/api-endpoints-list.md`

## Tests Added

- `tests/Feature/MerchantPortalPhaseOneTest.php`

Coverage includes:

- unauthenticated access
- internal user without membership
- active / invited / suspended / deleted membership behavior
- merchant override rejection
- duplicate membership blocking
- same-merchant restore behavior
- primary owner validation
- admin authorization
- capability mapping

## Manual Commands To Run

```powershell
php artisan migrate
php artisan db:seed --class=RolePermissionSeeder
php artisan test --filter=MerchantPortalPhaseOneTest
php artisan test
```

## Future Phases

- merchant portal orders
- merchant portal shipments
- merchant portal finance
- merchant portal integrations
- merchant portal exports and attachments
