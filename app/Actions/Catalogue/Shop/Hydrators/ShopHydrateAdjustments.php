<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 24 Feb 2025 13:47:29 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Ordering\Adjustment\AdjustmentTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\Adjustment;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopHydrateAdjustments implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Shop $shop): string
    {
        return $shop->id;
    }

    public function handle(Shop $shop): void
    {
        $stats = [
            'number_adjustments' => $shop->adjustments()->count(),
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'adjustments',
                field: 'type',
                enum: AdjustmentTypeEnum::class,
                models: Adjustment::class,
                where: function ($q) use ($shop) {
                    $q->where('shop_id', $shop->id);
                }
            )
        );

        $shop->stats()->update($stats);
    }


}
