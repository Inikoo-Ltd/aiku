<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Jun 2025 14:13:40 Central Indonesia Time, Shanghai, China
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\WebBlock;

use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Http\Resources\Web\WebBlockSubDepartmentsResource;
use App\Models\Web\Webpage;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebBlockSubDepartments
{
    use AsObject;

    public function handle(Webpage $webpage, array $webBlock): array
    {

        $subDepartments = DB::table('product_categories')->where('department_id', $webpage->model_id)
            ->leftjoin('webpages', function ($join) {
                $join->on('product_categories.id', '=', 'webpages.model_id')
                    ->where('webpages.model_type', '=', 'ProductCategory');
            })
            ->select(['product_categories.slug', 'product_categories.code', 'product_categories.name', 'product_categories.image_id', 'product_categories.image_id', 'webpages.url as url'])
            ->where('product_categories.type', ProductCategoryTypeEnum::SUB_DEPARTMENT)
            ->where('product_categories.show_in_website', true)
            ->whereNull('product_categories.deleted_at')
            ->get();

        $productRoute = [
            'name' => 'grp.json.product_category.products.index',
            'parameters' => [$webpage->model->slug],
        ];

        data_set($webBlock, 'web_block.layout.data.fieldValue',  $webpage->website->published_layout['sub_department']['data']['fieldValue'] ?? []);
        data_set($webBlock, 'web_block.layout.data.fieldValue.products_route', $productRoute);
        data_set($webBlock, 'web_block.layout.data.fieldValue.sub_departments', WebBlockSubDepartmentsResource::collection($subDepartments)->toArray(request()));

        return $webBlock;
    }

}
