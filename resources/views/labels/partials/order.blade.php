@if($config['company_name_enabled'])
    <div class="company">{{ $config['company_name'] }}</div>
@endif
<div class="title">{{ __('labels.order') }} {{ $label['order_number'] ?? __('common.not_available') }}</div>
<div class="meta"><strong>{{ __('labels.merchant') }}:</strong> {{ $label['merchant_name'] ?? __('common.not_available') }}</div>
<div class="meta"><strong>{{ __('labels.customer') }}:</strong> {{ $label['customer_name'] ?? __('common.not_available') }}</div>
<div class="meta"><strong>{{ __('labels.phone') }}:</strong> {{ $label['customer_phone'] ?? __('common.not_available') }}</div>
<div class="meta"><strong>{{ __('labels.items_count') }}:</strong> {{ $label['items_count'] ?? 0 }}</div>
<div class="badge-row">
    <span class="badge">{{ $label['fulfillment_type'] ?? 'N/A' }}</span>
    <span class="badge">{{ $label['order_type'] ?? 'N/A' }}</span>
</div>
<div class="meta"><strong>{{ __('labels.cod') }}:</strong> {{ $label['cod_amount'] ?? '0.00' }}</div>
<div class="meta address"><strong>{{ __('labels.delivery') }}:</strong> {{ $label['delivery_summary'] ?? __('common.not_available') }}</div>
<div class="barcode">{{ $label['barcode'] ?? __('common.not_available') }}</div>
