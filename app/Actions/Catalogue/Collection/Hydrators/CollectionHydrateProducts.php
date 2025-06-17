<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 17 Apr 2024 02:07:50 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Collection\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Models\Catalogue\Collection;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CollectionHydrateProducts implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Collection $collection): string
    {
        return $collection->id;
    }

    public function handle(Collection $collection): void
    {
        $stats = [
            'number_products' => $collection->products()->count(),
        ];

        $count = DB::table('collection_has_models')
            ->leftJoin('products', 'products.id', '=', 'collection_has_models.model_id')
            ->where('collection_id', $collection->id)
            ->where('model_type', 'Product')
            ->selectRaw("products.state as state, count(*) as total")
            ->groupBy('products.state')
            ->pluck('total', 'state')->all();
        foreach (ProductStateEnum::cases() as $case) {
            $stats["number_products_state_".$case->snake()] = Arr::get($count, $case->value, 0);
        }

        $collectionStats = $collection->stats;
        $collectionStats->update($stats);

        $changed = Arr::except($collectionStats->getChanges(), ['updated_at', 'last_fetched_at']);
        if (count($changed) > 0) {
            CollectionHydrateState::run($collection);
        }
    }

}
