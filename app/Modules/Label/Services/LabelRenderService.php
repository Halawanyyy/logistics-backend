<?php

namespace App\Modules\Label\Services;

use App\Modules\Label\Enums\LabelFormat;
use Symfony\Component\HttpFoundation\Response;

class LabelRenderService
{
    public function __construct(
        private readonly LabelHtmlService $labelHtmlService,
        private readonly LabelPdfService $labelPdfService,
        private readonly LabelSettingsService $labelSettingsService,
        private readonly LabelTsplService $labelTsplService,
        private readonly LabelZplService $labelZplService,
    ) {
    }

    /**
     * @param array<string, mixed> $label
     * @param array<string, mixed> $options
     */
    public function renderLabel(array $label, array $options = []): Response
    {
        return $this->renderLabels([$label], (string) $label['label_type'], $options);
    }

    /**
     * @param array<int, array<string, mixed>> $labels
     * @param array<string, mixed> $options
     */
    public function renderLabels(array $labels, string $labelType, array $options = []): Response
    {
        $format = $this->labelSettingsService->resolveFormat($options['format'] ?? null);
        $dimensions = $this->labelSettingsService->dimensionsFor(
            $labelType,
            isset($options['width_mm']) ? (float) $options['width_mm'] : null,
            isset($options['height_mm']) ? (float) $options['height_mm'] : null,
        );
        $settings = $this->labelSettingsService->options($dimensions);
        $filename = $options['filename'] ?? 'labels-'.$labelType;

        return match ($format) {
            LabelFormat::HTML => $this->htmlResponse(
                $this->labelHtmlService->renderLabels($labels, $settings),
                $filename,
                (bool) ($options['download'] ?? false)
            ),
            LabelFormat::PDF => $this->binaryResponse(
                $this->labelPdfService->renderLabels($labels, $settings),
                'application/pdf',
                $filename.'.pdf',
                (bool) ($options['download'] ?? false)
            ),
            LabelFormat::ZPL => $this->textResponse(
                $this->labelZplService->renderLabels($labels, $settings),
                $filename.'.zpl',
                (bool) ($options['download'] ?? false)
            ),
            LabelFormat::TSPL => $this->textResponse(
                $this->labelTsplService->renderLabels($labels, $settings),
                $filename.'.tspl',
                (bool) ($options['download'] ?? false)
            ),
            default => response()->json($this->jsonPayload($labels, $format, $dimensions)),
        };
    }

    /**
     * @param array<string, mixed> $manifest
     * @param array<string, mixed> $options
     */
    public function renderManifest(array $manifest, array $options = []): Response
    {
        $format = $this->labelSettingsService->resolveFormat($options['format'] ?? null);
        $dimensions = ['width_mm' => 210.0, 'height_mm' => 297.0];
        $settings = $this->labelSettingsService->options($dimensions);
        $filename = $options['filename'] ?? 'driver-manifest';

        return match ($format) {
            LabelFormat::HTML => $this->htmlResponse(
                $this->labelHtmlService->renderManifest($manifest, $settings),
                $filename,
                (bool) ($options['download'] ?? false)
            ),
            LabelFormat::PDF => $this->binaryResponse(
                $this->labelPdfService->renderManifest($manifest, $settings),
                'application/pdf',
                $filename.'.pdf',
                (bool) ($options['download'] ?? false)
            ),
            default => response()->json([
                'label_type' => $manifest['label_type'] ?? 'driver_manifest',
                'format' => LabelFormat::JSON,
                'width_mm' => $dimensions['width_mm'],
                'height_mm' => $dimensions['height_mm'],
                'barcode' => $manifest['barcode'] ?? null,
                'rendered_content' => null,
                'data' => $manifest,
            ]),
        };
    }

    private function htmlResponse(string $html, string $filename, bool $download): Response
    {
        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => ($download ? 'attachment' : 'inline').'; filename="'.$filename.'.html"',
        ]);
    }

    private function textResponse(string $content, string $filename, bool $download): Response
    {
        return response($content, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => ($download ? 'attachment' : 'inline').'; filename="'.$filename.'"',
        ]);
    }

    private function binaryResponse(string $content, string $contentType, string $filename, bool $download): Response
    {
        return response($content, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => ($download ? 'attachment' : 'inline').'; filename="'.$filename.'"',
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $labels
     * @param array{width_mm: float, height_mm: float} $dimensions
     * @return array<string, mixed>
     */
    private function jsonPayload(array $labels, string $format, array $dimensions): array
    {
        return [
            'label_type' => $labels[0]['label_type'] ?? 'unknown',
            'format' => $format,
            'width_mm' => $dimensions['width_mm'],
            'height_mm' => $dimensions['height_mm'],
            'barcode' => $labels[0]['barcode'] ?? null,
            'rendered_content' => null,
            'data' => count($labels) === 1 ? $labels[0] : $labels,
        ];
    }
}
