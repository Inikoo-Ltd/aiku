<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Jun 2025 14:27:00 Central Indonesia Time, Sanur, Shanghai, China
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\WebBlock;

use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Http\Resources\Web\WebBlockFamilyResource;
use App\Models\Web\Webpage;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebBlockFamily
{
    use AsObject;

    public function handle(Webpage $webpage, array $webBlock): array
    {
        $families = DB::table('product_categories')->where('sub_department_id', $webpage->model_id)
            ->leftjoin('webpages', function ($join) {
                $join->on('product_categories.id', '=', 'webpages.model_id')
                    ->where('webpages.model_type', '=', 'ProductCategory');
            })
            ->select(['product_categories.slug', 'product_categories.code', 'product_categories.image_id', 'product_categories.name', 'product_categories.image_id', 'webpages.url as url'])
            ->where('product_categories.type', ProductCategoryTypeEnum::FAMILY)
            ->where('product_categories.show_in_website', true)
            ->get();

        $productRoute = [
            'name' => 'grp.json.product_category.products.index',
            'parameters' => [$webpage->model->slug],
        ];

        data_set($webBlock, 'web_block.layout.data.fieldValue',  $webpage->website->published_layout['family']['data']['fieldValue'] ?? []);
        data_set($webBlock, 'web_block.layout.data.fieldValue.products_route', $productRoute);

        if (!$families->isEmpty()) {
            data_set($webBlock, 'web_block.layout.data.fieldValue.family', WebBlockFamilyResource::make($families)->toArray(request()));
        } else {
            data_set($webBlock, 'web_block.layout.data.fieldValue.family', []);
        }
        return $webBlock;
    }

}
