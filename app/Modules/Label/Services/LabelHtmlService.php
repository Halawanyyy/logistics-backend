<?php

namespace App\Modules\Label\Services;

use Illuminate\Contracts\View\Factory as ViewFactory;

class LabelHtmlService
{
    public function __construct(
        private readonly ViewFactory $viewFactory,
        private readonly Code128BarcodeService $code128BarcodeService,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $labels
     * @param  array<string, bool|float|int|string>  $settings
     */
    public function renderLabels(array $labels, array $settings): string
    {
        return $this->viewFactory->make('labels.sheet', [
            'labels' => $labels,
            'settings' => $settings,
        ])->render();
    }

    /**
     * @param  array<int, array<string, mixed>>  $labels
     * @param  array<string, bool|float|int|string>  $settings
     */
    public function renderSimpleWaybillLabels(array $labels, array $settings): string
    {
        $labels = array_map(function (array $label): array {
            $label['barcode_svg'] = $this->code128BarcodeService->svg((string) $label['barcode']);

            return $label;
        }, $labels);

        return $this->viewFactory->make('labels.simple-waybill', [
            'labels' => $labels,
            'settings' => $settings,
        ])->render();
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @param  array<string, bool|float|int|string>  $settings
     */
    public function renderManifest(array $manifest, array $settings): string
    {
        return $this->viewFactory->make('labels.driver-manifest', [
            'manifest' => $manifest,
            'settings' => $settings,
        ])->render();
    }
}
