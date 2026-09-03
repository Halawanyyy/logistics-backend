<?php

namespace App\Modules\Label\Services;

use App\Modules\Label\Enums\LabelFormat;
use App\Modules\Label\Enums\LabelType;
use App\Modules\Settings\Services\SystemSettingService;

class LabelSettingsService
{
    public function __construct(
        private readonly SystemSettingService $systemSettingService,
    ) {}

    public function resolveFormat(?string $format): string
    {
        if ($format !== null && in_array($format, LabelFormat::values(), true)) {
            return $format;
        }

        $default = (string) $this->systemSettingService->get('default_label_format', LabelFormat::HTML);

        return in_array($default, LabelFormat::values(), true) ? $default : LabelFormat::HTML;
    }

    /**
     * @return array{width_mm: float, height_mm: float}
     */
    public function dimensionsFor(string $labelType, ?float $widthOverride = null, ?float $heightOverride = null): array
    {
        [$defaultWidth, $defaultHeight] = match ($labelType) {
            LabelType::WAREHOUSE_PRODUCT => [
                (float) $this->systemSettingService->get('product_label_width_mm', 60),
                (float) $this->systemSettingService->get('product_label_height_mm', 40),
            ],
            LabelType::SHIPMENT, LabelType::WAYBILL => [
                (float) $this->systemSettingService->get('shipment_label_width_mm', 100),
                (float) $this->systemSettingService->get('shipment_label_height_mm', 150),
            ],
            LabelType::ORDER => [
                (float) $this->systemSettingService->get('order_label_width_mm', 60),
                (float) $this->systemSettingService->get('order_label_height_mm', 40),
            ],
            LabelType::WAREHOUSE_BIN => [
                (float) $this->systemSettingService->get('bin_label_width_mm', 50),
                (float) $this->systemSettingService->get('bin_label_height_mm', 30),
            ],
            default => [210.0, 297.0],
        };

        return [
            'width_mm' => $widthOverride ?? $defaultWidth,
            'height_mm' => $heightOverride ?? $defaultHeight,
        ];
    }

    /**
     * @param  array{width_mm: float, height_mm: float}  $dimensions
     * @return array<string, bool|float|int|string>
     */
    public function options(array $dimensions): array
    {
        return [
            'default_label_format' => $this->resolveFormat(null),
            'width_mm' => $dimensions['width_mm'],
            'height_mm' => $dimensions['height_mm'],
            'printer_dpi' => (int) $this->systemSettingService->get('printer_dpi', 203),
            'label_margin_mm' => (float) $this->systemSettingService->get('label_margin_mm', 2),
            'zpl_darkness' => (int) $this->systemSettingService->get('zpl_darkness', 15),
            'zpl_speed' => (int) $this->systemSettingService->get('zpl_speed', 4),
            'tspl_density' => (int) $this->systemSettingService->get('tspl_density', 8),
            'tspl_speed' => (int) $this->systemSettingService->get('tspl_speed', 4),
            'label_company_name_enabled' => (bool) $this->systemSettingService->get('label_company_name_enabled', true),
            'label_logo_enabled' => (bool) $this->systemSettingService->get('label_logo_enabled', false),
            'label_qr_enabled' => (bool) $this->systemSettingService->get('label_qr_enabled', false),
            'label_barcode_human_readable' => (bool) $this->systemSettingService->get('label_barcode_human_readable', true),
            'company_name' => (string) $this->systemSettingService->get('company_name', 'Fast Logistics'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function settingsPayload(): array
    {
        return [
            'supported_formats' => LabelFormat::values(),
            'supported_types' => LabelType::values(),
            'defaults' => [
                'format' => $this->resolveFormat(null),
                'default_label_width_mm' => (float) $this->systemSettingService->get('default_label_width_mm', 60),
                'default_label_height_mm' => (float) $this->systemSettingService->get('default_label_height_mm', 40),
                'product_label_width_mm' => (float) $this->systemSettingService->get('product_label_width_mm', 60),
                'product_label_height_mm' => (float) $this->systemSettingService->get('product_label_height_mm', 40),
                'shipment_label_width_mm' => (float) $this->systemSettingService->get('shipment_label_width_mm', 100),
                'shipment_label_height_mm' => (float) $this->systemSettingService->get('shipment_label_height_mm', 150),
                'order_label_width_mm' => (float) $this->systemSettingService->get('order_label_width_mm', 60),
                'order_label_height_mm' => (float) $this->systemSettingService->get('order_label_height_mm', 40),
                'bin_label_width_mm' => (float) $this->systemSettingService->get('bin_label_width_mm', 50),
                'bin_label_height_mm' => (float) $this->systemSettingService->get('bin_label_height_mm', 30),
                'printer_dpi' => (int) $this->systemSettingService->get('printer_dpi', 203),
                'label_margin_mm' => (float) $this->systemSettingService->get('label_margin_mm', 2),
                'zpl_darkness' => (int) $this->systemSettingService->get('zpl_darkness', 15),
                'zpl_speed' => (int) $this->systemSettingService->get('zpl_speed', 4),
                'tspl_density' => (int) $this->systemSettingService->get('tspl_density', 8),
                'tspl_speed' => (int) $this->systemSettingService->get('tspl_speed', 4),
                'label_company_name_enabled' => (bool) $this->systemSettingService->get('label_company_name_enabled', true),
                'label_logo_enabled' => (bool) $this->systemSettingService->get('label_logo_enabled', false),
                'label_qr_enabled' => (bool) $this->systemSettingService->get('label_qr_enabled', false),
                'label_barcode_human_readable' => (bool) $this->systemSettingService->get('label_barcode_human_readable', true),
            ],
        ];
    }
}
