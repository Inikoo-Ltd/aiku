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

    public function dimensions(string $dimensionsData): string
    {
        $data = json_decode($dimensionsData, true);
        if ($data) {
            $unit = Arr::get($data, 'units', 'm');
            return match (Arr::get($data, 'type')) {
                'rectangular' => number($data['l'] ?? 0).'x'.number($data['w'] ?? 0).'x'.number($data['h'] ?? 0).' ('.$unit.')',
                'sheet' => number($data['l'] ?? 0).'x'.number($data['w'] ?? 0).' ('.$unit.')',
                'cilinder', 'cylinder' => number($data['h'] ?? 0).'x'.number($data['w'] ?? 0).' ('.$unit.')',
                'sphere' => 'D:'.number($data['h'] ?? 0).' ('.$unit.')',
                'string' => 'L.'.number($data['l'] ?? 0).' ('.$unit.')',
                default => '',
            };
        }

        return '';
    }
}
