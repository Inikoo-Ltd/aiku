<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 15 Jul 2026 08:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterCollection;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class SearchMasterShop
{
    use AsAction;
    use WithRawSearchResults;

    public function handle(string $query, array $options = []): array
    {
        $masterShopId = Arr::get($options, 'master_shop_id');

        $mapCodeNameState = static fn (array $document) => [
            'id'    => (int)$document['id'],
            'code'  => $document['code'] ?? null,
            'name'  => $document['name'] ?? null,
            'state' => $document['state'] ?? null,
        ];

        $scoped = function (string $model) use ($query, $masterShopId) {
            $builder = $model::search($query);
            if ($masterShopId) {
                $builder->where('master_shop_id', $masterShopId);
            }

            return $builder;
        };

        return [
            'scope'   => 'master_shop',
            'results' => [
                'master_products'           => array_map($mapCodeNameState, $this->rawDocuments($scoped(MasterAsset::class))),
                'master_product_categories' => array_map($mapCodeNameState, $this->rawDocuments($scoped(MasterProductCategory::class))),
                'master_collections'        => array_map($mapCodeNameState, $this->rawDocuments($scoped(MasterCollection::class))),
            ],
        ];
    }


}
