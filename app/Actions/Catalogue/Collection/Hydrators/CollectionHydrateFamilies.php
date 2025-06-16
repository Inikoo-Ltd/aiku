<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Jun 2025 12:48:05 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Collection\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Models\Catalogue\Collection;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CollectionHydrateFamilies implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Collection $collection): string
    {
        return $collection->id;
    }

    public function handle(Collection $collection): void
    {

        $stats         = [
            'number_families'    => $collection->families()->count(),
        ];

        $count = DB::table('collection_has_models')
            ->leftJoin('product_categories', 'product_categories.id', '=', 'collection_has_models.model_id')
            ->where('collection_id', $collection->id)
            ->where('model_type', 'ProductCategory')
            ->selectRaw("product_categories.state as state, count(*) as total")
            ->groupBy('product_categories.state')
            ->pluck('total', 'state')->all();
        foreach (ProductCategoryStateEnum::cases() as $case) {
            $stats["number_families_state_".$case->snake()] = Arr::get($count, $case->value, 0);
        }


        $collection->stats->update($stats);
    }

}
