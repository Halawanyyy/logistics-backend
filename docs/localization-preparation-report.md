# Localization Preparation Report

## Summary

This phase adds additive Arabic/English localization support to the Laravel 12 logistics backend without changing stored business values, routes, enum values, or existing API shapes.

## Supported Locales

- `en`
- `ar`

## Locale Header Rules

- Primary override: `X-Locale`
- Secondary source: `Accept-Language`
- Fallback: `config('app.locale')`
- Unsupported locales fall back safely to the configured default locale.

## Files Added Or Updated

- Added request locale middleware:
  - `app/Http/Middleware/SetRequestLocale.php`
- Added localized enum/status label service:
  - `app/Services/LocalizedLabelService.php`
- Added language files under:
  - `lang/en/*`
  - `lang/ar/*`
- Updated selected auth, tracking, integration, merchant portal, request-validation, and resource files to use localized messages and additive `*_label` fields.
- Updated safe HTML label/document views to translate static headings.

## Translation File Structure

- `lang/en/common.php`
- `lang/ar/common.php`
- `lang/en/auth.php`
- `lang/ar/auth.php`
- `lang/en/orders.php`
- `lang/ar/orders.php`
- `lang/en/shipments.php`
- `lang/ar/shipments.php`
- `lang/en/warehouse.php`
- `lang/ar/warehouse.php`
- `lang/en/finance.php`
- `lang/ar/finance.php`
- `lang/en/merchant_portal.php`
- `lang/ar/merchant_portal.php`
- `lang/en/integrations.php`
- `lang/ar/integrations.php`
- `lang/en/labels.php`
- `lang/ar/labels.php`
- `lang/en/enums.php`
- `lang/ar/enums.php`
- `lang/en/validation.php`
- `lang/ar/validation.php`

## Localized Response Examples

Example with Arabic locale:

```json
{
  "status": "in_transit",
  "status_label": "قيد النقل"
}
```

Example with English locale:

```json
{
  "status": "in_transit",
  "status_label": "In Transit"
}
```

## Validation Localization

- Standard Laravel 422 validation behavior remains unchanged.
- Arabic and English validation attribute names are now provided for common fields.
- Custom cross-field validation messages used by order, shipment, and merchant portal requests are localized.

## Enum Label Coverage

Localized label groups were added for common response values including:

- order statuses, types, payment types, fulfillment types, sources, and warehouse fulfillment statuses
- shipment statuses
- driver statuses
- warehouse product, receipt, return, transfer, stock movement, and inbound-request statuses
- finance invoice, payout, and wallet transaction statuses/types/directions
- merchant portal roles and statuses
- merchant integration token statuses

Missing translations fall back to a humanized label rather than failing.

## Public Tracking

- Public tracking remains redacted.
- Safe localized fields now include localized status/timeline labels.
- Raw values remain unchanged.

## Label And Printing Notes

- Safe HTML label/document headings were localized.
- Raw enum/business values were not replaced.
- Printer-oriented plain text output was intentionally left conservative.

## Arabic Rendering Limitations

- Arabic rendering is safer in HTML/browser-based output than raw printer command formats.
- The current low-level invoice PDF renderer still uses a simple built-in font path, so full Arabic PDF text rendering should be treated as a future improvement.
- If ZPL/TSPL Arabic output is required later, it should be validated against the exact printer/font stack before enabling broader translation there.

## Manual Commands To Run

```bash
php artisan optimize:clear
php artisan test --filter=LocalizationPreparationTest
php artisan test
```

## Future Improvements

- Expand translation coverage for more secondary/operational messages over time.
- Add localized labels to any remaining low-priority resources that still expose only raw enum values.
- Upgrade PDF generation if full Arabic invoice rendering becomes a product requirement.
