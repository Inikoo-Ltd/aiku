<?php

/*
 * Author Louis Perez
 * Created on 11-09-2026-11h-25m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Enums\Goods\TradeUnit;

use App\Enums\EnumHelperTrait;

enum TradeUnitBestBeforeEnum: string
{
    use EnumHelperTrait;

    case CUSTOM = 'custom';
    case PAO_3M = 'pao_3m';
    case PAO_6M = 'pao_6m';
    case PAO_9M = 'pao_9m';
    case PAO_12M = 'pao_12m';
    case PAO_18M = 'pao_18m';
    case PAO_24M = 'pao_24m';
    case NO_EXPIRY_DATE = 'no_expiry_date';

    public static function labels(): array
    {
        return [
            'custom'         => __('Expiry Date'),
            'pao_3m'         => __('PAO 3M'),
            'pao_6m'         => __('PAO 6M'),
            'pao_9m'         => __('PAO 9M'),
            'pao_12m'        => __('PAO 12M'),
            'pao_18m'        => __('PAO 18M'),
            'pao_24m'        => __('PAO 24M'),
            'no_expiry_date' => __('No Expiry Date'),
        ];
    }

    public static function options(): array
    {
        return array_map(
            fn (self $bestBefore) => [
                'value' => $bestBefore->value,
                'label' => self::labels()[$bestBefore->value],
            ],
            self::cases()
        );
    }

    public static function bestBeforeFromLabelInfo(?array $labelInfo): array
    {
        $bestBefore = self::tryFrom((string) data_get($labelInfo, 'best_before'));

        return [
            'show'  => $bestBefore !== null,
            'value' => $bestBefore ? [
                'value' => $bestBefore->value,
                'label' => self::labels()[$bestBefore->value],
            ] : null,
        ];
    }
}
