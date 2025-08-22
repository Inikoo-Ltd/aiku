<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Aug 2025 18:33:58 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Helpers;

use Lorisleiva\Actions\Concerns\AsObject;

class DimensionsFormatter
{
    use AsObject;

    public function dimensions($dimensionsData)
    {
        $data = json_decode($dimensionsData, true);
        if ($data) {
            return match ($data['type']) {
                'rectangular' => number(
                    convertUnits(
                        $data['l'],
                        'm',
                        $data['units']
                    )
                ).'x'.number(
                    convertUnits(
                        $data['w'],
                        'm',
                        $data['units']
                    )
                ).'x'.number(
                    convertUnits(
                        $data['h'],
                        'm',
                        $data['units']
                    )
                ).' ('.$data['units'].')',
                'sheet' => number(
                    convertUnits(
                        $data['l'],
                        'm',
                        $data['units']
                    )
                ).'x'.number(
                    convertUnits(
                        $data['w'],
                        'm',
                        $data['units']
                    )
                ).' ('.$data['units'].')',
                'cilinder' => number(
                    convertUnits(
                        $data['h'],
                        'm',
                        $data['units']
                    )
                ).'x'.number(
                    convertUnits(
                        $data['w'],
                        'm',
                        $data['units']
                    )
                ).' ('.$data['units'].')',
                'sphere' => 'D:'.number(
                    convertUnits(
                        $data['h'],
                        'm',
                        $data['units']
                    )
                ).' ('.$data['units'].')',
                'string' => 'L.'.number(
                    convertUnits(
                        $data['l'],
                        'm',
                        $data['units']
                    )
                ).' ('.$data['units'].')',
                default => '',
            };
        }
    }

}
