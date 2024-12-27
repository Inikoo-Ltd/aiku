<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Dec 2024 16:01:56 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\MasterShop\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Models\Goods\MasterShop;
use App\Models\Catalogue\Shop;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class MasterShopHydrateShops
{
    use AsAction;
    use WithEnumStats;

    private MasterShop $masterShop;

    public function __construct(MasterShop $masterShop)
    {
        $this->masterShop = $masterShop;
    }

    public function getJobMiddleware(): array
    {
        return [(new WithoutOverlapping($this->masterShop->id))->dontRelease()];
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
