<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 24 Jun 2026 12:54:27 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Catalogue\Review;

use App\Enums\EnumHelperTrait;

enum ReviewAutoPublishingEnum: string
{
    use EnumHelperTrait;

    case IMMEDIATELY = 'immediately';
    case DELAY = 'delay';
    case NEVER = 'never';

    public static function labels(): array
    {
        return [
            self::IMMEDIATELY->value => __('Immediately'),
            self::DELAY->value       => __('Delay'),
            self::NEVER->value       => __('Never'),
        ];
    }

    public static function selectOptions(): array
    {
        $options = [];
        foreach (self::labels() as $value => $label) {
            $options[] = [
                'value' => $value,
                'label' => $label,
            ];
        }

        return $options;
    }
}
