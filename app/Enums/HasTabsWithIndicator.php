<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 20 Mar 2023 19:10:54 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums;

use App\Models\Dispatching\DeliveryNote;

trait HasTabsWithIndicator
{
    public static function navigation(DeliveryNote $parent): array
    {
        return collect(self::cases())->mapWithKeys(function ($case) use ($parent) {
            return  [$case->value => $case->blueprint($parent)];
        })->all();
    }

    public static function navigationExcept(DeliveryNote $parent, array $excludes): array
    {
        return collect(self::cases())
        ->filter(fn ($case) => !in_array($case, $excludes))
        ->mapWithKeys(function ($case) use ($parent) {
            return  [$case->value => $case->blueprint($parent)];
        })->all();
    }
}
