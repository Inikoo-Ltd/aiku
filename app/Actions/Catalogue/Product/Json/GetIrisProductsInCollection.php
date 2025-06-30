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

        $queryBuilder->join('collection_has_models', function ($join) use ($collection) {
            $join->on('products.id', '=', 'collection_has_models.model_id')
                ->where('collection_has_models.model_type', '=', 'Product')
                ->where('collection_has_models.collection_id', '=', $collection->id);
        });
        $queryBuilder->select($this->getSelect());
        $queryBuilder->selectRaw('\''.request()->path().'\' as parent_url');

        if ($collection->stats->number_products > 0) {
            $prePage = 50;
        } else {
            $prePage = 20;
        }

        return $this->getData($queryBuilder, $prePage);
    }


    public function asController(Collection $collection, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);

        return $this->handle(collection: $collection);
    }

}
