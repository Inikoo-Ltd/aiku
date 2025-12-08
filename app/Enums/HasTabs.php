<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 20 Mar 2023 19:10:54 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums;

trait HasTabs
{
    public static function navigation(): array
    {
        return collect(self::cases())->mapWithKeys(function ($case) {
            return [$case->value => $case->blueprint()];
        })->all();
    }
}
