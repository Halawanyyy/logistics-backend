<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('labels.driver_manifest') }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 12mm;
        }

        body {
            margin: 0;
            padding: 0;
            color: #111827;
            font-family: "Segoe UI", Arial, sans-serif;
            background: #ffffff;
        }

        h1 {
            margin: 0 0 6px;
            font-size: 24px;
        }

        .subtitle {
            margin-bottom: 16px;
            color: #4b5563;
        }

        .meta {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px 16px;
            margin-bottom: 20px;
        }

        .meta-item {
            font-size: 13px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        th, td {
            border: 1px solid #d1d5db;
            padding: 8px;
            vertical-align: top;
            text-align: left;
        }

        th {
            background: #f3f4f6;
        }
    </style>
</head>
<body>
    <h1>{{ $manifest['title'] }}</h1>
    <div class="subtitle">{{ $manifest['subtitle'] }}</div>

    <div class="meta">
        @foreach ($manifest['lines'] as $line)
            <div class="meta-item"><strong>{{ $line['label'] }}:</strong> {{ $line['value'] }}</div>
        @endforeach
    </div>

    <table>
        <thead>
            <tr>
                <th>{{ __('labels.shipment') }}</th>
                <th>{{ __('labels.order') }}</th>
                <th>{{ __('labels.merchant') }}</th>
                <th>{{ __('labels.customer') }}</th>
                <th>{{ __('labels.phone') }}</th>
                <th>{{ __('labels.address') }}</th>
                <th>{{ __('labels.cod') }}</th>
            </tr>
        </thead>
        <tbody>
        @foreach ($manifest['items'] as $item)
            <tr>
                <td>{{ $item['shipment_number'] }}</td>
                <td>{{ $item['order_number'] ?? __('common.not_available') }}</td>
                <td>{{ $item['merchant_name'] ?? __('common.not_available') }}</td>
                <td>{{ $item['customer_name'] }}</td>
                <td>{{ $item['customer_phone'] }}</td>
                <td>{{ $item['delivery_address'] }}</td>
                <td>{{ $item['cod_amount'] }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</body>
</html>
