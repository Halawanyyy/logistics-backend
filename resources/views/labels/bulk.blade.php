<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Labels</title>
    <style>
        @page {
            size: {{ $config['width_mm'] }}mm {{ $config['height_mm'] }}mm;
            margin: {{ $config['margin_mm'] }}mm;
        }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; color: #111827; }
        .label-page {
            width: {{ $config['width_mm'] }}mm;
            min-height: {{ $config['height_mm'] }}mm;
            page-break-after: always;
            padding: {{ $config['margin_mm'] }}mm;
        }
        .label-page:last-child { page-break-after: auto; }
        .label {
            width: 100%;
            min-height: calc({{ $config['height_mm'] }}mm - ({{ $config['margin_mm'] }}mm * 2));
            border: 1px solid #d1d5db;
            padding: 4mm;
            display: flex;
            flex-direction: column;
            gap: 2mm;
        }
        .company { font-size: 10px; color: #4b5563; text-transform: uppercase; }
        .title { font-size: 18px; font-weight: 700; line-height: 1.2; }
        .meta { font-size: 11px; line-height: 1.4; }
        .badge-row { display: flex; gap: 4px; flex-wrap: wrap; }
        .badge {
            border: 1px solid #9ca3af;
            border-radius: 999px;
            padding: 2px 6px;
            font-size: 10px;
            font-weight: 600;
        }
        .barcode {
            margin-top: auto;
            border-top: 1px dashed #9ca3af;
            padding-top: 3mm;
            font-size: 16px;
            font-weight: 700;
            letter-spacing: 1px;
            word-break: break-word;
        }
        .address { white-space: pre-line; }
    </style>
</head>
<body>
@foreach ($labels as $label)
    <div class="label-page">
        <div class="label">
            @include('labels.partials.'.$labelType, ['label' => $label, 'config' => $config])
        </div>
    </div>
@endforeach
</body>
</html>
