<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 01 Jun 2024 19:35:06 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\TimeZone\UI;

use App\Models\Helpers\Timezone;
use Lorisleiva\Actions\Concerns\AsObject;

class GetTimeZonesOptionsComplexData
{
    use AsObject;

    public function handle(): array
    {

        $selectOptions = [];
        /** @var Timezone $timezone */
        foreach (Timezone::all() as $timezone) {

            $selectOptions[] = [
                'label' => $this->formatLabel($timezone->name, $timezone->offset),
                'value' => $timezone->name,
            ];
        }

        return $selectOptions;
    }

    private function formatLabel(string $tz, int $offsetSeconds): string
    {
        $prettyTz = str_replace(['_', '/'], [' ', ' / '], $tz);
        return $this->formatGmtOffset($offsetSeconds) . ' ' . $prettyTz;
    }

    private function formatGmtOffset(int $offsetSeconds): string
    {
        $sign   = $offsetSeconds >= 0 ? '+' : '-';
        $abs    = abs($offsetSeconds);
        $hours  = intdiv($abs, 3600);
        $minutes = intdiv($abs % 3600, 60);
        return sprintf('GMT%s%02d:%02d', $sign, $hours, $minutes);
    }
}
