@if($config['company_name_enabled'])
    <div class="company">{{ $config['company_name'] }}</div>
@endif
<div class="title">{{ $label['bin_name'] ?? __('labels.warehouse_bin') }}</div>
<div class="meta"><strong>{{ __('labels.warehouse') }}:</strong> {{ $label['warehouse_name'] ?? __('common.not_available') }}</div>
<div class="meta"><strong>{{ __('labels.zone') }}:</strong> {{ $label['zone_name'] ?? __('common.not_available') }} @if(!empty($label['zone_code']))({{ $label['zone_code'] }})@endif</div>
<div class="meta"><strong>{{ __('labels.shelf') }}:</strong> {{ $label['shelf_name'] ?? __('common.not_available') }} @if(!empty($label['shelf_code']))({{ $label['shelf_code'] }})@endif</div>
<div class="meta"><strong>{{ __('labels.bin_code') }}:</strong> {{ $label['bin_code'] ?? __('common.not_available') }}</div>
<div class="barcode">{{ $label['barcode'] ?? __('common.not_available') }}</div>
