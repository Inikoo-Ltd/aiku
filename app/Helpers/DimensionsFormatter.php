<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Aug 2025 18:33:58 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Helpers;

use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

class DimensionsFormatter
{
    use AsObject;

    private const array PER_METRE = ['mm' => 1000, 'cm' => 100, 'm' => 1, 'inch' => 39.3701];

    /**
     * Dimensions are stored in metres; units is only the unit they are shown in.
     *
     * @param array<string, mixed> $dimensions sizes as typed in $dimensions['units']
     * @return array<string, mixed>
     */
    public static function toMetres(array $dimensions): array
    {
        $perMetre = self::PER_METRE[Arr::get($dimensions, 'units')] ?? 1;

        foreach (['l', 'w', 'h'] as $size) {
            if (is_numeric(Arr::get($dimensions, $size))) {
                $dimensions[$size] = round($dimensions[$size] / $perMetre, 6);
            }
        }

        return $dimensions;
    }

    /**
     * @param array<string, mixed> $dimensions stored in metres
     * @return array<string, mixed> sizes in $dimensions['units']
     */
    public static function fromMetres(array $dimensions): array
    {
        $perMetre = self::PER_METRE[Arr::get($dimensions, 'units')] ?? 1;

        foreach (['l', 'w', 'h'] as $size) {
            if (is_numeric(Arr::get($dimensions, $size))) {
                $dimensions[$size] = round($dimensions[$size] * $perMetre, 4);
            }
        }

        return $dimensions;
    }

    public function dimensions(string $dimensionsData): string
    {
        $data = json_decode($dimensionsData, true);
        if (!is_array($data) || !$data) {
            return '';
        }

        $data  = self::fromMetres($data);
        $units = ' ('.Arr::get($data, 'units', 'm').')';
        $size  = fn (string $side) => number((float) ($data[$side] ?? 0));

        return match (Arr::get($data, 'type')) {
            'rectangular'          => $size('l').'x'.$size('w').'x'.$size('h').$units,
            'sheet'                => $size('l').'x'.$size('w').$units,
            'cilinder', 'cylinder' => $size('h').'x'.$size('w').$units,
            'sphere'               => 'D:'.$size('h').$units,
            'string'               => 'L.'.$size('l').$units,
            default                => '',
        };
    }
}
