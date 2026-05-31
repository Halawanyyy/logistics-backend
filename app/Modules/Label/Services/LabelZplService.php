<?php

namespace App\Modules\Label\Services;

class LabelZplService
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
        $dpi = max(203, (int) $settings['printer_dpi']);
        $widthDots = (int) round(((float) $settings['width_mm']) * $dpi / 25.4);
        $heightDots = (int) round(((float) $settings['height_mm']) * $dpi / 25.4);
        $humanReadable = (bool) $settings['label_barcode_human_readable'] ? 'Y' : 'N';
        $barcodeHeight = max(80, (int) round($heightDots * 0.18));
        $y = 24;
        $commands = [
            '^XA',
            '^CI28',
            '^PW'.$widthDots,
            '^LL'.$heightDots,
            '^MD'.(int) $settings['zpl_darkness'],
            '^PR'.(int) $settings['zpl_speed'],
            '^LH0,0',
            '^FO24,'.$y.'^A0N,30,28^FD'.$this->escape((string) $label['title']).'^FS',
        ];

        $y += 34;

        if (($label['subtitle'] ?? null) !== null) {
            $commands[] = '^FO24,'.$y.'^A0N,24,24^FD'.$this->escape((string) $label['subtitle']).'^FS';
            $y += 28;
        }

        foreach (array_slice($label['lines'] ?? [], 0, 7) as $line) {
            $commands[] = '^FO24,'.$y.'^A0N,24,22^FD'.$this->escape($line['label'].': '.$line['value']).'^FS';
            $y += 28;
        }

        $barcodeY = max($y + 8, $heightDots - $barcodeHeight - 80);
        $commands[] = '^FO24,'.$barcodeY.'^BY2,2,'.$barcodeHeight.'^BCN,'.$barcodeHeight.','.$humanReadable.',N,N^FD'.$this->escape((string) ($label['barcode'] ?? '')).'^FS';

        if (! (bool) $settings['label_barcode_human_readable']) {
            $commands[] = '^FO24,'.($barcodeY + $barcodeHeight + 8).'^A0N,24,24^FD'.$this->escape((string) ($label['barcode'] ?? '')).'^FS';
        }

        $commands[] = '^XZ';

        return implode("\n", $commands);
    }

    private function escape(string $value): string
    {
        return str_replace(['^', '~'], [' ', ' '], $value);
    }
}
