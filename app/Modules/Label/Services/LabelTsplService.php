<?php

namespace App\Modules\Label\Services;

class LabelTsplService
{
    /**
     * @param array<int, array<string, mixed>> $labels
     * @param array<string, bool|float|int|string> $settings
     */
    public function renderLabels(array $labels, array $settings): string
    {
        return implode("\n", array_map(fn (array $label): string => $this->renderSingle($label, $settings), $labels));
    }

    /**
     * @param array<string, mixed> $label
     * @param array<string, bool|float|int|string> $settings
     */
    private function renderSingle(array $label, array $settings): string
    {
        $width = (float) $settings['width_mm'];
        $height = (float) $settings['height_mm'];
        $barcodeHeight = max(60, (int) round($height * 3.5));
        $humanReadable = (bool) $settings['label_barcode_human_readable'] ? 1 : 0;
        $y = 20;
        $commands = [
            sprintf('SIZE %.2f mm,%.2f mm', $width, $height),
            'GAP 2 mm,0 mm',
            'DIRECTION 1',
            'REFERENCE 0,0',
            'CLS',
            'SPEED '.(int) $settings['tspl_speed'],
            'DENSITY '.(int) $settings['tspl_density'],
            'TEXT 20,20,"0",0,1,1,"'.$this->escape((string) $label['title']).'"',
        ];

        $y += 30;

        if (($label['subtitle'] ?? null) !== null) {
            $commands[] = 'TEXT 20,'.$y.',"0",0,1,1,"'.$this->escape((string) $label['subtitle']).'"';
            $y += 26;
        }

        foreach (array_slice($label['lines'] ?? [], 0, 7) as $line) {
            $commands[] = 'TEXT 20,'.$y.',"0",0,1,1,"'.$this->escape($line['label'].': '.$line['value']).'"';
            $y += 24;
        }

        $commands[] = 'BARCODE 20,'.$y.',"128",'.$barcodeHeight.','.$humanReadable.',0,2,2,"'.$this->escape((string) ($label['barcode'] ?? '')).'"';
        $commands[] = 'PRINT 1,1';

        return implode("\n", $commands);
    }

    private function escape(string $value): string
    {
        return str_replace('"', "'", $value);
    }
}
