<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 Jul 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Catalogue\Review;

use App\Enums\EnumHelperTrait;

enum ReviewValidationScopeEnum: string
{
    use EnumHelperTrait;

    case ORGANISATION = 'organisation';
    case GROUP = 'group';

    public static function labels(): array
    {
        return [
            self::ORGANISATION->value => __('Organisation'),
            self::GROUP->value        => __('Group'),
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
