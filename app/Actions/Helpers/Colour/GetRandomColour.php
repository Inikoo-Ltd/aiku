<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 02 May 2025 12:13:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Colour;

use Lorisleiva\Actions\Concerns\AsObject;

class GetRandomColour
{
    use AsObject;

    /**
     * @throws \Random\RandomException
     */
    public function handle(): string
    {
        // Generate random hue (0-360)
        $hue = random_int(0, 360);

        // Set saturation to moderate value for pastels (40-70%)
        $saturation = random_int(40, 70);

        // Set lightness high for pastels (75-90%)
        $lightness = random_int(75, 90);

        // Generate random opacity between 0.8 and 1.0
        $opacity = number_format(random_int(80, 100) / 100, 2);

        $rgb = $this->hslToRgb($hue, $saturation, $lightness);

        return "rgba({$rgb['r']}, {$rgb['g']}, {$rgb['b']}, $opacity)";
    }

    private function hslToRgb(int $h, int $s, int $l): array
    {
        $h /= 360;
        $s /= 100;
        $l /= 100;

        if ($s == 0) {
            $r = $g = $b = $l;
        } else {
            $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
            $p = 2 * $l - $q;
            $r = $this->hue2rgb($p, $q, $h + 1 / 3);
            $g = $this->hue2rgb($p, $q, $h);
            $b = $this->hue2rgb($p, $q, $h - 1 / 3);
        }

        return [
            'r' => round($r * 255),
            'g' => round($g * 255),
            'b' => round($b * 255)
        ];
    }

    private function hue2rgb(float $p, float $q, float $t): float
    {
        if ($t < 0) {
            $t += 1;
        }
        if ($t > 1) {
            $t -= 1;
        }

        $result = $p; // The default result matches the last return

        if ($t < 1 / 6) {
            $result = $p + ($q - $p) * 6 * $t;
        } elseif ($t < 1 / 2) {
            $result = $q;
        } elseif ($t < 2 / 3) {
            $result = $p + ($q - $p) * (2 / 3 - $t) * 6;
        }

        return $result;
    }
}
