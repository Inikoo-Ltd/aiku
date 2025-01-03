<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 20 Mar 2023 19:10:54 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums;

use App\Models\Catalogue\Collection;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Fulfilment\PalletReturn;

trait HasTabsWithQuantity
{
    public static function navigation(PalletReturn|PalletDelivery|Collection|Pallet $parent): array
    {
        return collect(self::cases())->mapWithKeys(function ($case) use ($parent) {
            return  [$case->value => $case->blueprint($parent)];
        })->all();
    }
}
