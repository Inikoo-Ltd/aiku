<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 16 May 2025 12:28:40 Central Indonesia Time, Beach Office, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\WebBlock;

use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Http\Resources\Web\WebBlockDepartmentsResource;
use App\Models\Web\Webpage;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebBlockDepartments
{
    use AsObject;

    public function handle(Webpage $webpage, array $webBlock): array
    {

        $departments = DB::table('product_categories')->where('shop_id', $webpage->shop_id)
            ->select(['slug', 'code', 'name', 'image_id'])
            ->where('type', ProductCategoryTypeEnum::DEPARTMENT)
            ->where('show_in_website', true)
            ->whereNull('deleted_at')
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

        data_set($webBlock, 'web_block.layout.data.permissions', $permissions);
        data_set($webBlock, 'web_block.layout.data.fieldValue',  $webpage->website->published_layout['department']['data']['fieldValue'] ?? []);
        data_set($webBlock, 'web_block.layout.data.fieldValue.products_route', $productRoute);
        data_set($webBlock, 'web_block.layout.data.fieldValue.departments', WebBlockDepartmentsResource::collection($departments)->toArray(request()));

        return $webBlock;
    }

}
