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
            $rawAbbr       = $withoutTimestamp[0];

            /* Cookies written before the AI channel existed file ChatGPT and its kind as a referral
               from a website, and they live for four months. Read as AI here, at the one point every
               consumer goes through, so a registration from such a cookie can no longer mint a
               Referral campaign for an assistant. `raw_abbr` keeps what the string actually said. */
            if (in_array($type, [TrafficSourcesTypeEnum::REFERRAL, TrafficSourcesTypeEnum::ORGANIC_SEARCH], true)
                && GetTrafficSourceFromRefererHeader::isAiAssistantHost($campaignRef)) {
                $type = TrafficSourcesTypeEnum::AI;
            }

            $touches[] = [
                'timestamp'    => $timestampPart === '' ? null : (int) $timestampPart,
                'abbr'         => TrafficSourcesTypeEnum::abbr()[$type->value],
                'raw_abbr'     => $rawAbbr,
                'type'         => $type,
                'campaign_ref' => $campaignRef,
            ];
        }

        return $touches;
    }

    /**
     * The touches back as the string the column stores, with every translated abbreviation made
     * permanent, so the stored history says what it means.
     *
     * @param array<int, array{timestamp: int|null, abbr: string, campaign_ref: string|null}> $touches
     */
    public static function serialise(array $touches): string
    {
        return implode('|', array_map(
            fn (array $touch) => ($touch['timestamp'] ?? '').$touch['abbr'].($touch['campaign_ref'] ?? ''),
            $touches
        ));
    }

    /**
     * Whether any touch read differently from how it was written.
     *
     * @param array<int, array{abbr: string, raw_abbr: string}> $touches
     */
    public static function wasTranslated(array $touches): bool
    {
        return (bool) array_filter($touches, fn (array $touch) => $touch['abbr'] !== $touch['raw_abbr']);
    }
}
