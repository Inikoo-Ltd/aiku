<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 05 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\TrafficSource;

use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use Lorisleiva\Actions\Concerns\AsAction;

class ParseTrafficSourceTouches
{
    use AsAction;

    /**
     * Parses the raw `aiku_tsd` cookie/legacy text value into an ordered list of marketing touches.
     *
     * Each segment has the shape `<timestamp><abbr><campaign_ref?>`, segments being separated by `|` or `,`.
     *
     * @return array<int, array{timestamp: int|null, abbr: string, type: TrafficSourcesTypeEnum, campaign_ref: string|null}>
     */
    public function handle(?string $data): array
    {
        if (!is_string($data) || blank($data)) {
            return [];
        }

        $segments = preg_split('/[|,]/', $data) ?: [];
        $touches  = [];

        foreach ($segments as $segment) {
            $segment = trim($segment);

            if (blank($segment)) {
                continue;
            }

            $withoutTimestamp = ltrim($segment, '0123456789');

            if (strlen($withoutTimestamp) === 0) {
                continue;
            }

            $type = TrafficSourcesTypeEnum::fromAbbr($withoutTimestamp[0]);

            if ($type === null) {
                continue;
            }

            $timestampPart = substr($segment, 0, strlen($segment) - strlen($withoutTimestamp));
            $campaignRef   = strlen($withoutTimestamp) > 1 ? substr($withoutTimestamp, 1) : null;

            $touches[] = [
                'timestamp'    => $timestampPart === '' ? null : (int) $timestampPart,
                'abbr'         => $withoutTimestamp[0],
                'type'         => $type,
                'campaign_ref' => $campaignRef,
            ];
        }

        return $touches;
    }
}
