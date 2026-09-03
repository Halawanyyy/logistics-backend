<?php

namespace App\Modules\Label\Services;

class LabelPdfService
{
    public function __construct(
        private readonly Code128BarcodeService $code128BarcodeService,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $labels
     * @param  array<string, bool|float|int|string>  $settings
     */
    public function renderLabels(array $labels, array $settings): string
    {
        $pages = array_map(function (array $label) use ($settings): array {
            return [
                'width_mm' => (float) $settings['width_mm'],
                'height_mm' => (float) $settings['height_mm'],
                'margin_mm' => (float) $settings['label_margin_mm'],
                'font_size' => 8,
                'line_spacing' => 11,
                'lines' => $this->labelLines($label),
            ];
        }, $labels);

        return $this->buildPdf($pages);
    }

    /**
     * @param  array<int, array<string, mixed>>  $labels
     * @param  array<string, bool|float|int|string>  $settings
     */
    public function renderSimpleWaybillLabels(array $labels, array $settings): string
    {
        $pages = array_map(fn (array $label): array => [
            'width_mm' => (float) $settings['width_mm'],
            'height_mm' => (float) $settings['height_mm'],
            'margin_mm' => (float) $settings['label_margin_mm'],
            'font_size' => 18,
            'line_spacing' => 22,
            'lines' => [],
            'simple_waybill_number' => (string) $label['waybill_number'],
        ], $labels);

        return $this->buildPdf($pages);
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @param  array<string, bool|float|int|string>  $settings
     */
    public function renderManifest(array $manifest, array $settings): string
    {
        $lines = [
            $manifest['title'],
            $manifest['subtitle'] ?? '',
            '',
        ];

        foreach ($manifest['lines'] as $line) {
            $lines[] = sprintf('%s: %s', $line['label'], $line['value']);
        }

        $lines[] = '';
        $lines[] = 'Shipments:';

        foreach ($manifest['items'] as $item) {
            $lines[] = sprintf(
                '%s | %s | %s | COD %s',
                $item['shipment_number'] ?? '-',
                $item['customer_name'] ?? '-',
                $item['customer_phone'] ?? '-',
                $item['cod_amount'] ?? '0.00'
            );
            $lines[] = sprintf(
                'Order %s | Merchant %s',
                $item['order_number'] ?? '-',
                $item['merchant_name'] ?? '-'
            );
            $lines[] = sprintf('Address: %s', $item['delivery_address'] ?? '-');
            $lines[] = '';
        }

        $pages = [];
        foreach (array_chunk($lines, 34) as $pageLines) {
            $pages[] = [
                'width_mm' => 210.0,
                'height_mm' => 297.0,
                'margin_mm' => 10.0,
                'font_size' => 9,
                'line_spacing' => 13,
                'lines' => $pageLines,
            ];
        }

        return $this->buildPdf($pages);
    }

    /**
     * @param  array<string, mixed>  $label
     * @return list<string>
     */
    private function labelLines(array $label): array
    {
        $lines = [
            (string) $label['title'],
        ];

        if (($label['subtitle'] ?? null) !== null) {
            $lines[] = (string) $label['subtitle'];
        }

        foreach ($label['lines'] ?? [] as $line) {
            $lines[] = sprintf('%s: %s', $line['label'], $line['value']);
        }

        $lines[] = 'Barcode: '.($label['barcode'] ?? 'N/A');

        return array_map(fn (string $line): string => $this->truncate($line), $lines);
    }

    private function truncate(string $value, int $limit = 90): string
    {
        if (mb_strlen($value) <= $limit) {
            return $value;
        }

        return mb_substr($value, 0, $limit - 3).'...';
    }

    /**
     * @param  array<int, array{width_mm: float, height_mm: float, margin_mm: float, font_size: int, line_spacing: int, lines: array<int, string>}>  $pages
     */
    private function buildPdf(array $pages): string
    {
        $objects = [];
        $pageObjectNumbers = [];
        $contentObjectNumbers = [];
        $fontObjectNumber = 3;
        $nextObjectNumber = 4;

        foreach ($pages as $index => $page) {
            $pageObjectNumbers[$index] = $nextObjectNumber++;
            $contentObjectNumbers[$index] = $nextObjectNumber++;
        }

        $objects[1] = '1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj';
        $kids = implode(' ', array_map(fn (int $objectNumber): string => $objectNumber.' 0 R', $pageObjectNumbers));
        $objects[2] = sprintf('2 0 obj << /Type /Pages /Kids [%s] /Count %d >> endobj', $kids, count($pageObjectNumbers));
        $objects[$fontObjectNumber] = '3 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj';

        foreach ($pages as $index => $page) {
            $pageObject = $pageObjectNumbers[$index];
            $contentObject = $contentObjectNumbers[$index];
            $stream = $this->pageStream($page);
            $widthPoints = $this->mmToPoints($page['width_mm']);
            $heightPoints = $this->mmToPoints($page['height_mm']);

            $objects[$pageObject] = sprintf(
                '%d 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2f %.2f] /Resources << /Font << /F1 3 0 R >> >> /Contents %d 0 R >> endobj',
                $pageObject,
                $widthPoints,
                $heightPoints,
                $contentObject
            );

            $objects[$contentObject] = sprintf(
                '%d 0 obj << /Length %d >> stream'."\n".'%s'."\n".'endstream endobj',
                $contentObject,
                strlen($stream),
                $stream
            );
        }

        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $objectNumber => $object) {
            $offsets[$objectNumber] = strlen($pdf);
            $pdf .= $object."\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".($nextObjectNumber)."\n";
        $pdf .= "0000000000 65535 f \n";

        for ($index = 1; $index < $nextObjectNumber; $index++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$index] ?? 0);
        }

        $pdf .= sprintf("trailer << /Size %d /Root 1 0 R >>\n", $nextObjectNumber);
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }

    /**
     * @param  array{height_mm: float, margin_mm: float, font_size: int, line_spacing: int, lines: array<int, string>}  $page
     */
    private function pageStream(array $page): string
    {
        if (isset($page['simple_waybill_number'])) {
            return $this->simpleWaybillPageStream($page);
        }

        $x = $this->mmToPoints($page['margin_mm']);
        $y = $this->mmToPoints($page['height_mm']) - $this->mmToPoints($page['margin_mm']) - $page['font_size'];
        $streamLines = [];

        foreach ($page['lines'] as $line) {
            $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line);
            $streamLines[] = sprintf('BT /F1 %d Tf %.2f %.2f Td (%s) Tj ET', $page['font_size'], $x, $y, $escaped);
            $y -= $page['line_spacing'];

            if ($y < $this->mmToPoints($page['margin_mm'])) {
                break;
            }
        }

        return implode("\n", $streamLines);
    }

    /** @param array<string, mixed> $page */
    private function simpleWaybillPageStream(array $page): string
    {
        $number = (string) $page['simple_waybill_number'];
        $layout = $this->code128BarcodeService->layout($number);
        $pageWidth = $this->mmToPoints((float) $page['width_mm']);
        $pageHeight = $this->mmToPoints((float) $page['height_mm']);
        $margin = $this->mmToPoints((float) $page['margin_mm']);
        $availableWidth = $pageWidth - ($margin * 2);
        $moduleWidth = $availableWidth / $layout['modules'];
        $barcodeHeight = min($this->mmToPoints(35), $pageHeight * 0.32);
        $barcodeY = ($pageHeight - $barcodeHeight) / 2;
        $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $number);
        $estimatedTextWidth = strlen($number) * 9;
        $textX = max($margin, ($pageWidth - $estimatedTextWidth) / 2);
        $stream = [
            sprintf('BT /F1 18 Tf %.2f %.2f Td (%s) Tj ET', $textX, $barcodeY + $barcodeHeight + 28, $escaped),
            '0 g',
        ];

        foreach ($layout['bars'] as $bar) {
            $stream[] = sprintf(
                '%.3f %.3f %.3f %.3f re f',
                $margin + ($bar['offset'] * $moduleWidth),
                $barcodeY,
                $bar['width'] * $moduleWidth,
                $barcodeHeight,
            );
        }

        $stream[] = sprintf('BT /F1 12 Tf %.2f %.2f Td (%s) Tj ET', max($margin, ($pageWidth - (strlen($number) * 6)) / 2), $barcodeY - 18, $escaped);

        return implode("\n", $stream);
    }

    private function mmToPoints(float $mm): float
    {
        return $mm * 72 / 25.4;
    }
}
