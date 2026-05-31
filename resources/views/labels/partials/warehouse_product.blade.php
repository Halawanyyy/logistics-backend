@if($config['company_name_enabled'])
    <div class="company">{{ $config['company_name'] }}</div>
@endif
<div class="title">{{ $label['product_name'] ?? __('labels.warehouse_product') }}</div>
<div class="meta"><strong>{{ __('labels.merchant') }}:</strong> {{ $label['merchant_name'] ?? __('common.not_available') }}</div>
<div class="meta"><strong>{{ __('labels.sku') }}:</strong> {{ $label['sku'] ?? __('common.not_available') }}</div>
<div class="meta"><strong>{{ __('labels.warehouse') }}:</strong> {{ $label['warehouse_name'] ?? __('common.not_available') }}</div>
<div class="meta"><strong>{{ __('labels.bin') }}:</strong> {{ $label['bin_code'] ?? __('common.not_available') }}</div>
<div class="meta"><strong>{{ __('labels.reference') }}:</strong> {{ $label['product_reference'] ?? __('common.not_available') }}</div>
<div class="barcode">{{ $label['barcode'] ?? __('common.not_available') }}</div>
