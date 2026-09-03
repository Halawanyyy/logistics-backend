<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $labels[0]['waybill_number'] ?? '' }}</title>
    <style>
        @page {
            size: {{ $settings['width_mm'] }}mm {{ $settings['height_mm'] }}mm;
            margin: {{ $settings['label_margin_mm'] }}mm;
        }

        * { box-sizing: border-box; }
        body { margin: 0; padding: 0; color: #000; background: #fff; font-family: Arial, sans-serif; }
        .label-page {
            width: {{ $settings['width_mm'] }}mm;
            min-height: {{ $settings['height_mm'] }}mm;
            padding: {{ $settings['label_margin_mm'] }}mm;
            page-break-after: always;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .label-page:last-child { page-break-after: auto; }
        .simple-waybill { width: 88%; text-align: center; }
        .waybill-number { margin-bottom: 7mm; font-size: 8mm; line-height: 1; font-weight: 700; }
        .barcode { width: 100%; height: 28mm; }
        .barcode svg { display: block; width: 100%; height: 100%; }
        .human-readable { margin-top: 3mm; font-family: "Courier New", monospace; font-size: 5mm; font-weight: 700; letter-spacing: .4mm; }
    </style>
</head>
<body>
@foreach ($labels as $label)
    <div class="label-page">
        <main class="simple-waybill">
            <div class="waybill-number">{{ $label['waybill_number'] }}</div>
            <div class="barcode">{!! $label['barcode_svg'] !!}</div>
            <div class="human-readable">{{ $label['waybill_number'] }}</div>
        </main>
    </div>
@endforeach
</body>
</html>
