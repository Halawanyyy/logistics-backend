<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Labels</title>
    <style>
        @page {
            size: {{ $settings['width_mm'] }}mm {{ $settings['height_mm'] }}mm;
            margin: {{ $settings['label_margin_mm'] }}mm;
        }

        body {
            margin: 0;
            padding: 0;
            color: #111827;
            font-family: "Segoe UI", Arial, sans-serif;
            background: #ffffff;
        }

        .label-page {
            width: {{ $settings['width_mm'] }}mm;
            min-height: {{ $settings['height_mm'] }}mm;
            box-sizing: border-box;
            padding: {{ $settings['label_margin_mm'] }}mm;
            page-break-after: always;
            display: flex;
            align-items: stretch;
        }

        .label-page:last-child {
            page-break-after: auto;
        }

        .label-card {
            width: 100%;
            border: 1px solid #d1d5db;
            padding: 3mm;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            gap: 1.5mm;
        }

        .company {
            font-size: 3mm;
            color: #6b7280;
        }

        .title {
            font-size: 4.2mm;
            font-weight: 700;
            margin: 0;
            line-height: 1.2;
        }

        .subtitle {
            font-size: 3.4mm;
            color: #374151;
            margin: 0;
        }

        .reference {
            font-size: 3mm;
            color: #6b7280;
        }

        .line {
            font-size: 3.1mm;
            line-height: 1.35;
            word-break: break-word;
        }

        .line strong {
            font-weight: 700;
        }

        .barcode-block {
            margin-top: auto;
            border-top: 1px dashed #9ca3af;
            padding-top: 2mm;
        }

        .barcode-text {
            font-family: "Courier New", monospace;
            font-size: 4mm;
            letter-spacing: 0.4mm;
            font-weight: 700;
            word-break: break-all;
        }
    </style>
</head>
<body>
@foreach ($labels as $label)
    <div class="label-page">
        <div class="label-card">
            @if ($settings['label_company_name_enabled'])
                <div class="company">{{ $settings['company_name'] }}</div>
            @endif
            <h1 class="title">{{ $label['title'] }}</h1>
            @if (! empty($label['subtitle']))
                <div class="subtitle">{{ $label['subtitle'] }}</div>
            @endif
            @if (! empty($label['reference']))
                <div class="reference">{{ $label['reference'] }}</div>
            @endif

            @foreach ($label['lines'] ?? [] as $line)
                <div class="line">
                    <strong>{{ $line['label'] }}:</strong> {{ $line['value'] }}
                </div>
            @endforeach

            <div class="barcode-block">
                <div class="line"><strong>Barcode:</strong></div>
                <div class="barcode-text">{{ $label['barcode'] ?? 'N/A' }}</div>
            </div>
        </div>
    </div>
@endforeach
</body>
</html>
