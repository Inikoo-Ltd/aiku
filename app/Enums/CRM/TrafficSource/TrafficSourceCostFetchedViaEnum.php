<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Enums\CRM\TrafficSource;

use App\Enums\EnumHelperTrait;

/**
 * How a day of advertising spend reached us.
 *
 * The same day can be offered twice: an ad platform script pushing it and our own API pull fetching
 * it are reporting the same figure from the same account. The cost row is keyed on source, campaign
 * and date, so the second arrival is never a second row, but it does decide which figure is kept,
 * and that is what this column answers.
 */
enum TrafficSourceCostFetchedViaEnum: string
{
    use EnumHelperTrait;

    case API      = 'api';
    case WEBHOOK  = 'webhook';
    case IMPORT   = 'import';

    public static function labels(): array
    {
        return [
            'api'     => __('API'),
            'webhook' => __('Platform script'),
            'import'  => __('Manual import'),
        ];
    }
}
