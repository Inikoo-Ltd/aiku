<?php

/*
 * Author Louis Perez
 * Created on 11-09-2026-11h-52m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Enums\Goods\TradeUnit;

use App\Enums\EnumHelperTrait;

enum TradeUnitLabelPresenceEnum: string
{
    use EnumHelperTrait;

    case CE_MARKING = 'ce_marking';
    case UKCA_MARKING = 'ukca_marking';
    case WEEE_SYMBOL = 'weee_symbol';
    case IP_RATING = 'ip_rating';
    case SORTING_RECYCLING_INFORMATION = 'sorting_recycling_information';
    case SAFETY_ICONS = 'safety_icons';
    case BATCH_NUMBER = 'batch_number';

    public static function labels(): array
    {
        return [
            'ce_marking'                    => __('CE Markings'),
            'ukca_marking'                  => __('UKCA Markings'),
            'weee_symbol'                   => __('WEEE Symbol'),
            'ip_rating'                     => __('IP Rating'),
            'sorting_recycling_information' => __('Sorting / Recycling Information'),
            'safety_icons'                  => __('Safety Icons'),
            'batch_number'                  => __('Batch Number'),
        ];
    }

    public static function presenceFromLabelInfo(?array $labelInfo): array
    {
        $presence = [];

        foreach (self::values() as $field) {
            $presence[$field] = [
                'show' => data_get($labelInfo, $field, false) === true,
            ];
        }

        return $presence;
    }
}
