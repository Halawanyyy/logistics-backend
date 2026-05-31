<?php

namespace App\Modules\Label\Services;

use Illuminate\Contracts\View\Factory as ViewFactory;

class LabelHtmlService
{
    public function __construct(
        private readonly ViewFactory $viewFactory,
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $labels
     * @param array<string, bool|float|int|string> $settings
     */
    public function renderLabels(array $labels, array $settings): string
    {
        return $this->viewFactory->make('labels.sheet', [
            'labels' => $labels,
            'settings' => $settings,
        ])->render();
    }

    /**
     * @param array<string, mixed> $manifest
     * @param array<string, bool|float|int|string> $settings
     */
    public function renderManifest(array $manifest, array $settings): string
    {
        return $this->viewFactory->make('labels.driver-manifest', [
            'manifest' => $manifest,
            'settings' => $settings,
        ])->render();
    }
}
