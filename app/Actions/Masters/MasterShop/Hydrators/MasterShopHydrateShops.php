<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Dec 2024 23:18:23 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterShop\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Masters\MasterShop;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class MasterShopHydrateShops implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(MasterShop $masterShop): string
    {
        return $masterShop->id;
    }

    public function handle(MasterShop $masterShop): void
    {
        $stats = [
            'number_shops' => DB::table('shops')->where('master_shop_id', $masterShop->id)->count(),
            'number_current_shops' => DB::table('shops')->where('master_shop_id', $masterShop->id)->whereIn('state', [
                ShopStateEnum::OPEN,
                ShopStateEnum::CLOSING_DOWN,
            ])->count()
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'shops',
                field: 'state',
                enum: ShopStateEnum::class,
                models: Shop::class,
                where: function ($q) use ($masterShop) {
                    $q->where('master_shop_id', $masterShop->id);
                }
            )
        );


        $masterShop->stats()->update($stats);
    }


}
