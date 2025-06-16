<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:15:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Organisation\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\SysAdmin\Organisation;
use App\Models\Catalogue\Shop;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class OrganisationHydrateShops implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Organisation $organisation): string
    {
        return $organisation->id;
    }


    public function handle(Organisation $organisation): void
    {
        $stats = [
            'number_shops' => DB::table('shops')->where('organisation_id', $organisation->id)->count(),
            'number_current_shops' => DB::table('shops')->where('organisation_id', $organisation->id)->whereIn('state', [
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
                where: function ($q) use ($organisation) {
                    $q->where('organisation_id', $organisation->id);
                }
            )
        );

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'shops',
                field: 'type',
                enum: ShopTypeEnum::class,
                models: Shop::class,
                where: function ($q) use ($organisation) {
                    $q->where('organisation_id', $organisation->id);
                }
            )
        );

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'current_shops',
                field: 'type',
                enum: ShopTypeEnum::class,
                models: Shop::class,
                where: function ($q) use ($organisation) {
                    $q->where('organisation_id', $organisation->id)->whereIn('state', [
                        ShopStateEnum::OPEN,
                        ShopStateEnum::CLOSING_DOWN,
                    ]);
                }
            )
        );


        $organisation->catalogueStats()->update($stats);
    }
}
