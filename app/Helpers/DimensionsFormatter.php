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

    public function dimensions(string $dimensionsData)
    {
        $data = json_decode($dimensionsData, true);
        if ($data) {
            return match ($data['type']) {
                'rectangular' => number(
                    convertUnits(
                        $data['l'] ?? 0,
                        'm',
                        Arr::get($data, 'units', 'm')
                    )
                ).'x'.number(
                    convertUnits(
                        $data['w'] ?? 0,
                        'm',
                        Arr::get($data, 'units', 'm')
                    )
                ).'x'.number(
                    convertUnits(
                        $data['h'] ?? 0,
                        'm',
                        Arr::get($data, 'units', 'm')
                    )
                ).' ('.Arr::get($data, 'units', 'm').')',
                'sheet' => number(
                    convertUnits(
                        $data['l'] ?? 0,
                        'm',
                        Arr::get($data, 'units', 'm')
                    )
                ).'x'.number(
                    convertUnits(
                        $data['w'] ?? 0,
                        'm',
                        Arr::get($data, 'units', 'm')
                    )
                ).' ('.Arr::get($data, 'units', 'm').')',
                'cilinder' => number(
                    convertUnits(
                        $data['h'] ?? 0,
                        'm',
                        Arr::get($data, 'units', 'm')
                    )
                ).'x'.number(
                    convertUnits(
                        $data['w'] ?? 0,
                        'm',
                        Arr::get($data, 'units', 'm')
                    )
                ).' ('.Arr::get($data, 'units', 'm').')',
                'sphere' => 'D:'.number(
                    convertUnits(
                        $data['h'] ?? 0,
                        'm',
                        Arr::get($data, 'units', 'm')
                    )
                ).' ('.Arr::get($data, 'units', 'm').')',
                'string' => 'L.'.number(
                    convertUnits(
                        $data['l'] ?? 0,
                        'm',
                        Arr::get($data, 'units', 'm')
                    )
                ).' ('.Arr::get($data, 'units', 'm').')',
                default => '',
            };
        }
    }

}
