# Production Readiness Checklist

This checklist focuses on safe backend production hardening for the current Laravel 12 logistics API.

## 1. Environment And Config

- [ ] Set `APP_ENV=production`
- [ ] Set `APP_DEBUG=false`
- [ ] Set a real `APP_URL`
- [ ] Set `APP_TIMEZONE` explicitly for the deployment region
- [ ] Set `APP_LOCALE` and `APP_FALLBACK_LOCALE` explicitly for the deployed languages
- [ ] Set a strong `APP_KEY`
- [ ] Set `APP_PREVIOUS_KEYS` only when rotating keys
- [ ] Review `FRONTEND_URL`, `ADMIN_FRONTEND_URL`, and `MERCHANT_PORTAL_URL`
- [ ] Review `SANCTUM_STATEFUL_DOMAINS` if any browser SPA uses cookie-based auth
- [ ] Review `SESSION_DOMAIN`, `SESSION_SECURE_COOKIE`, and `SESSION_SAME_SITE`

## 2. CORS And Browser Auth

- [x] `config/cors.php` is present and covers `api/*` and `sanctum/csrf-cookie`
- [ ] Set `CORS_ALLOWED_ORIGINS` to the exact frontend origins
- [ ] Set `CORS_SUPPORTS_CREDENTIALS=true` only when Sanctum SPA cookies are required
- [ ] If using cross-site SPA cookies, set `SESSION_SECURE_COOKIE=true`
- [ ] If using cross-site SPA cookies, set `SESSION_SAME_SITE=none`

## 3. Storage And Private Files

- [x] Attachments are stored on the `attachments` disk
- [x] Exports are stored on the `exports` disk
- [x] Invoice PDFs are stored under the private `local` disk
- [ ] Ensure `storage/app/private`, `storage/app/attachments`, and `storage/app/exports` are backed up
- [ ] Ensure those directories are not directly exposed by the web server
- [ ] Review retention and cleanup policy for exports, attachments, and generated invoices
- [x] Internal attachment/export/invoice resources no longer expose raw `file_path`

## 4. Rate Limiting

- [x] `POST /api/auth/login` uses `throttle:auth-login`
- [x] `GET /api/track/{shipment_number}` uses `throttle:public-tracking`
- [x] `/api/integrations/*` token routes use `throttle:integration-api`
- [ ] Review the configured limits for real traffic
- [ ] Add upstream edge or WAF throttling for public endpoints if internet-exposed

## 5. Queue And Scheduler

- [x] No custom scheduled tasks were found in this pass
- [ ] Confirm whether exports, notifications, and invoices should remain synchronous
- [ ] If queues are enabled in production, run workers under a supervisor
- [ ] Verify failed-job storage and monitoring for the chosen queue backend

## 6. Logging And Monitoring

- [ ] Choose production log routing, such as `LOG_STACK=daily` or centralized logging
- [ ] Review `LOG_LEVEL` for production noise vs. signal
- [ ] Confirm log retention with `LOG_DAILY_DAYS`
- [ ] Add alerting for 5xx spikes, queue failures, and disk growth

## 7. Deploy And Backup

- [ ] Run migrations with maintenance planning as needed
- [ ] Clear and rebuild config/route caches after config changes
- [ ] Verify writable permissions for `storage/` and `bootstrap/cache/`
- [ ] Verify database backups
- [ ] Verify private file backups
- [ ] Verify recovery for generated invoices and exports if those files are business-critical

## 8. Manual Verification

- [ ] Run `php artisan optimize:clear`
- [ ] Run `php artisan test --filter=ProductionReadinessTest`
- [ ] Run `php artisan test`
