<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 16 May 2025 14:17:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\WebBlock;

use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Web\Webpage\WebpageSubTypeEnum;
use App\Http\Resources\Web\WebBlockFamiliesResource;
use App\Models\Web\Webpage;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebBlockFamilies
{
    use AsObject;

    public function handle(Webpage $webpage, array $webBlock): array
    {
        $families = DB::table('product_categories')
            ->leftJoin('webpages', function ($join) {
                $join->on('product_categories.id', '=', 'webpages.model_id')
                    ->where('webpages.model_type', '=', 'ProductCategory');
            })
            ->select(['product_categories.code', 'name', 'image_id', 'url', 'title'])
            ->where($webpage->sub_type == WebpageSubTypeEnum::DEPARTMENT ? 'product_categories.department_id' : 'product_categories.sub_department_id', $webpage->model_id)
            ->where('product_categories.type', ProductCategoryTypeEnum::FAMILY)
            ->where('show_in_website', true)
            ->whereNull('product_categories.deleted_at')
            ->get();

        $productRoute = [
            'workshop' => [
                'name' => 'grp.json.product_category.products.index',
                'parameters' => [$webpage->model->slug],
            ],
            'iris' => [
                'name' => 'iris.json.product_category.products.index',
                'parameters' => [$webpage->model->slug],
            ],
        ];

        $permissions =  [];

        if($webpage->sub_type == WebpageSubTypeEnum::DEPARTMENT) {
            $permissions = ['hidden'];
        }

        data_set($webBlock, 'web_block.layout.data.permissions', $permissions);
        data_set($webBlock, 'web_block.layout.data.fieldValue',  $webpage->website->published_layout['family']['data']['fieldValue'] ?? []);
        data_set($webBlock, 'web_block.layout.data.fieldValue.products_route', $productRoute);
        data_set($webBlock, 'web_block.layout.data.fieldValue.families', WebBlockFamiliesResource::collection($families)->toArray(request()));

        return $webBlock;
    }

}
