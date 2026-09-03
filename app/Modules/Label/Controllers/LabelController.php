<?php

namespace App\Modules\Label\Controllers;

use App\Modules\Label\Enums\LabelType;
use App\Modules\Label\Requests\LabelPreviewRequest;
use App\Modules\Label\Services\LabelDataBuilderService;
use App\Modules\Label\Services\LabelRenderService;
use App\Modules\Label\Services\LabelSettingsService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class LabelController
{
    public function __construct(
        private readonly LabelDataBuilderService $labelDataBuilderService,
        private readonly LabelRenderService $labelRenderService,
        private readonly LabelSettingsService $labelSettingsService,
    ) {}

    public function templates(): JsonResponse
    {
        return response()->json([
            'data' => [
                'templates' => [
                    ['type' => LabelType::WAREHOUSE_PRODUCT, 'name' => 'Warehouse Product Label'],
                    ['type' => LabelType::SHIPMENT, 'name' => 'Shipment / Waybill Label'],
                    ['type' => LabelType::ORDER, 'name' => 'Order Label'],
                    ['type' => LabelType::WAREHOUSE_BIN, 'name' => 'Warehouse Bin Label'],
                    ['type' => LabelType::DRIVER_MANIFEST, 'name' => 'Driver Manifest Document'],
                    ['type' => LabelType::WAYBILL, 'name' => 'Pre-Printed Waybill Label'],
                ],
                ...$this->labelSettingsService->settingsPayload(),
            ],
        ]);
    }

    public function settings(): JsonResponse
    {
        return response()->json([
            'data' => $this->labelSettingsService->settingsPayload(),
        ]);
    }

    public function preview(LabelPreviewRequest $request): Response
    {
        $validated = $request->validated();
        $labelType = (string) ($validated['label_type'] ?? LabelType::SHIPMENT);
        $payload = $this->labelDataBuilderService->preview($labelType, $validated);

        if ($labelType === LabelType::DRIVER_MANIFEST) {
            return $this->labelRenderService->renderManifest($payload, [
                ...$validated,
                'filename' => 'label-preview-'.$labelType,
            ]);
        }

        return $this->labelRenderService->renderLabel($payload, [
            ...$validated,
            'filename' => 'label-preview-'.$labelType,
        ]);
    }

    public function test(LabelPreviewRequest $request): Response
    {
        $validated = $request->validated();
        $labelType = (string) ($validated['label_type'] ?? LabelType::WAREHOUSE_PRODUCT);
        $payload = $this->labelDataBuilderService->preview($labelType, $validated);

        if ($labelType === LabelType::DRIVER_MANIFEST) {
            return $this->labelRenderService->renderManifest($payload, [
                ...$validated,
                'filename' => 'label-test-'.$labelType,
            ]);
        }

        return $this->labelRenderService->renderLabel($payload, [
            ...$validated,
            'filename' => 'label-test-'.$labelType,
        ]);
    }
}
