<?php

/*
 * Author Louis Perez
 * Created on 11-09-2026-14h-39m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Enums\Goods\TradeUnit;

use App\Enums\EnumHelperTrait;

enum TradeUnitMarketEnum: string
{
    use EnumHelperTrait;

    case UK = 'uk';
    case EU = 'eu';
    case ES = 'es';

    public static function labels(): array
    {
        return [
            'uk' => __('UK'),
            'eu' => __('EU'),
            'es' => __('ES'),
        ];
    }

    public static function checkboxValue(?array $selectedMarkets): array
    {
        $selectedMarkets = (array) $selectedMarkets;

        return array_map(
            fn (self $market) => [
                'label' => self::labels()[$market->value],
                'key'   => $market->value,
                'value' => in_array($market->value, $selectedMarkets, true),
            ],
            self::cases()
        );
    }

    public static function marketsFromLabelInfo(?array $labelInfo): array
    {
        $selectedMarkets = (array) data_get($labelInfo, 'markets', []);

        $markets = array_values(array_map(
            fn (self $market) => [
                'value' => $market->value,
                'label' => self::labels()[$market->value],
            ],
            array_filter(self::cases(), fn (self $market) => in_array($market->value, $selectedMarkets, true))
        ));

        return [
            'show'  => $markets !== [],
            'value' => $markets,
        ];
    }
}
