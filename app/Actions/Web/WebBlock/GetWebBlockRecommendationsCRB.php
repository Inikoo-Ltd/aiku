<?php

/*
 * Author: Vika Aqordi
 * Created on 02-02-2026-14h-54m
 * Github: https://github.com/aqordeon
 * Copyright: 2026
*/

namespace App\Actions\Web\WebBlock;

use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebBlockRecommendationsCRB
{
    use AsObject;

    public function handle(Webpage $webpage, array $webBlock): array
    {
        // Recommendations: Customer Recently Bought
        $family = null;

        // Page: Family
        if ($webpage->model instanceof ProductCategory) {
            $family = [
                'id'    => $webpage->model->id,
                'slug'  => $webpage->model->slug,
            ];

            data_set($webBlock, 'web_block.layout.data.fieldValue.family', $family);
        }

        if ($webpage->model->family_id) {
            $family = [
                'id'    => $webpage->model->family_id,
                'name'  => $webpage->model->name,
                'slug'  => $webpage->model->slug,
            ];

            data_set($webBlock, 'web_block.layout.data.fieldValue.family', $family);
        }

        // Page: Product
        if ($webpage->model instanceof Product) {
            $product = [
                'id'    => $webpage->model->id
            ];

            data_set($webBlock, 'web_block.layout.data.fieldValue.product', $product);
        }

        return $webBlock;
    }

}
