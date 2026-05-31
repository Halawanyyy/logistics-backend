<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('labels.warehouse_barcode_labels') }}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 16px; color: #111827; }
        .sheet { display: flex; flex-wrap: wrap; gap: 12px; }
        .label { width: 320px; border: 1px solid #d1d5db; padding: 12px; page-break-inside: avoid; }
        .label h2 { margin: 0 0 8px; font-size: 16px; }
        .meta { font-size: 12px; line-height: 1.5; }
        .barcode { margin-top: 12px; padding-top: 12px; border-top: 1px dashed #9ca3af; font-size: 18px; letter-spacing: 2px; font-weight: bold; }
    </style>
</head>
<body>
<div class="sheet">
    @foreach ($labels as $label)
        <div class="label">
            <h2>{{ $label['warehouse_product']->name }}</h2>
            <div class="meta"><strong>{{ __('labels.merchant') }}:</strong> {{ $label['warehouse_product']->merchant?->name }}</div>
            <div class="meta"><strong>{{ __('labels.sku') }}:</strong> {{ $label['warehouse_product']->sku ?? __('common.not_available') }}</div>
            <div class="meta"><strong>{{ __('labels.barcode') }}:</strong> {{ $label['warehouse_product']->barcode }}</div>
            <div class="meta"><strong>{{ __('labels.warehouse') }}:</strong> {{ $label['warehouse_name'] ?? __('common.not_available') }}</div>
            <div class="barcode">{{ $label['warehouse_product']->barcode }}</div>
        </div>
    @endforeach
</div>
</body>
</html>
