<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 03 Dec 2025 14:59:04 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

/*
 * Author: Junie (AI Assistant)
 * Created: Wed, 03 Dec 2025 14:57:00 Local Time
 */

namespace App\Actions\Helpers\TimeZone\Json;

use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class IndexTimeZones
{
    use AsAction;

    /**
     * Return a curated, searchable list of common timezones.
     *
     * Query params:
     * - q: optional search text (case-insensitive) matched against label and value.
     */
    public function handle(?string $q = null): array
    {
        $now = CarbonImmutable::now();

        $timezones = [
            // Global
            'UTC',

            // Europe
            'Europe/London', 'Europe/Lisbon', 'Europe/Dublin', 'Europe/Madrid', 'Europe/Paris', 'Europe/Berlin', 'Europe/Rome', 'Europe/Amsterdam', 'Europe/Brussels', 'Europe/Prague', 'Europe/Warsaw', 'Europe/Bratislava', 'Europe/Athens', 'Europe/Helsinki', 'Europe/Bucharest', 'Europe/Istanbul',

            // Americas
            'America/Halifax', 'America/New_York', 'America/Toronto', 'America/Chicago', 'America/Denver', 'America/Phoenix', 'America/Los_Angeles', 'America/Anchorage', 'America/Adak', 'Pacific/Honolulu',
            'America/Mexico_City', 'America/Bogota', 'America/Lima', 'America/Santiago', 'America/Argentina/Buenos_Aires', 'America/Sao_Paulo',

            // Africa & Middle East
            'Africa/Cairo', 'Africa/Johannesburg', 'Africa/Nairobi', 'Africa/Lagos', 'Asia/Dubai', 'Asia/Riyadh', 'Asia/Jerusalem',

            // Asia
            'Asia/Tehran', 'Asia/Karachi', 'Asia/Kolkata', 'Asia/Kathmandu', 'Asia/Dhaka', 'Asia/Bangkok', 'Asia/Jakarta', 'Asia/Makassar', 'Asia/Singapore', 'Asia/Kuala_Lumpur', 'Asia/Hong_Kong', 'Asia/Shanghai', 'Asia/Taipei', 'Asia/Seoul', 'Asia/Tokyo',

            // Oceania
            'Australia/Perth', 'Australia/Brisbane', 'Australia/Sydney', 'Australia/Melbourne', 'Pacific/Auckland'
        ];

        $data = array_map(function (string $tz) use ($now): array {
            $zone   = new DateTimeZone($tz);
            $offset = $zone->getOffset($now);
            $label  = $this->formatLabel($tz, $offset);

            return [
            'value'        => $tz,
            'label'        => $label,
            'offset'       => $offset, // seconds from UTC
            'offset_label' => $this->formatGmtOffset($offset),
            ];
        }, $timezones);

        usort($data, function (array $a, array $b): int {
            return $a['offset'] <=> $b['offset'];
        });

        if ($q !== null && $q !== '') {
            $needle = Str::lower($q);
            $data   = array_values(array_filter($data, function (array $row) use ($needle): bool {
                return Str::contains(Str::lower($row['label']), $needle)
                    || Str::contains(Str::lower($row['value']), $needle)
                    || Str::contains(Str::lower($row['offset_label']), $needle);
            }));
        }

        return $data;
    }

    public function asController(): JsonResponse
    {
        // $q = request()->string('q')->toString();
        $q = request()->input('filter.global');  // from parameter ?filter[global]=

        return response()->json($this->handle($q));
    }

    private function formatLabel(string $tz, int $offsetSeconds): string
    {
        $prettyTz = str_replace(['_', '/'], [' ', ' / '], $tz);
        return $this->formatGmtOffset($offsetSeconds).' '.$prettyTz;
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
