@if($config['company_name_enabled'])
    <div class="company">{{ $config['company_name'] }}</div>
@endif
<div class="title">{{ __('labels.shipment') }} {{ $label['shipment_number'] ?? __('common.not_available') }}</div>
<div class="meta"><strong>{{ __('labels.order') }}:</strong> {{ $label['order_number'] ?? __('common.not_available') }}</div>
<div class="meta"><strong>{{ __('labels.merchant') }}:</strong> {{ $label['merchant_name'] ?? __('common.not_available') }}</div>
<div class="meta"><strong>{{ __('labels.customer') }}:</strong> {{ $label['customer_name'] ?? __('common.not_available') }}</div>
<div class="meta"><strong>{{ __('labels.phone') }}:</strong> {{ $label['customer_phone'] ?? __('common.not_available') }}</div>
<div class="meta"><strong>{{ __('labels.location') }}:</strong> {{ $label['delivery_governorate_name'] ?? '' }} {{ $label['delivery_area_name'] ?? '' }}</div>
<div class="meta address"><strong>{{ __('labels.address') }}:</strong> {{ $label['delivery_address'] ?? __('common.not_available') }}</div>
<div class="meta"><strong>{{ __('labels.cod') }}:</strong> {{ $label['cod_amount'] ?? '0.00' }}</div>
<div class="badge-row">
    <span class="badge">{{ $label['payment_type'] ?? 'N/A' }}</span>
    <span class="badge">{{ $label['order_type'] ?? 'N/A' }}</span>
    <span class="badge">{{ $label['fulfillment_type'] ?? 'N/A' }}</span>
    @if(!empty($label['is_fragile']))<span class="badge">{{ __('labels.fragile') }}</span>@endif
    @if(!empty($label['allow_inspection']))<span class="badge">{{ __('labels.inspection') }}</span>@endif
</div>
@if(!empty($label['notes']))
    <div class="meta"><strong>{{ __('labels.notes') }}:</strong> {{ $label['notes'] }}</div>
@endif
<div class="barcode">{{ $label['barcode'] ?? __('common.not_available') }}</div>
