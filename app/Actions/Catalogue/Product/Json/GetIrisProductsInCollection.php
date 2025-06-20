<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 19 Jun 2025 16:42:09 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product\Json;

use App\Actions\IrisAction;
use App\Models\Catalogue\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\ActionRequest;

class GetIrisProductsInCollection extends IrisAction
{
    use WithIrisProductsInWebpage;

    public function handle(Collection $collection, $stockMode = 'all'): LengthAwarePaginator
    {
        $queryBuilder = $this->getBaseQuery($stockMode);

        $queryBuilder->join('model_has_collections', function ($join) use ($collection) {
            $join->on('products.id', '=', 'model_has_collections.model_id')
                ->where('model_has_collections.model_type', '=', 'Product')
                ->where('model_has_collections.collection_id', '=', $collection->id);
        });


        return $this->getData($queryBuilder);
    }


    public function asController(Collection $collection, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);

        return $this->handle(collection: $collection);
    }

}
