<?php
/*
 * author Arya Permana - Kirin
 * created on 10-06-2025-15h-20m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Web\WebBlock;

use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Http\Resources\Web\WebBlockCollectionResource;
use App\Http\Resources\Web\WebBlockSubDepartmentsResource;
use App\Models\Web\Webpage;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebBlockCollections
{
    use AsObject;

    public function handle(Webpage $webpage, array $webBlock): array
    {

        $collection = $webpage->model;

        // $productRoute = [
        //     'workshop' => [
        //         'name' => 'grp.json.product_category.products.index',
        //         'parameters' => [$webpage->model->slug],
        //     ],
        //     'iris' => [
        //         'name' => 'iris.json.product_category.products.index',
        //         'parameters' => [$webpage->model->slug],
        //     ],
        // ];

        $permissions =  [];

        data_set($webBlock, 'web_block.layout.data.permissions', $permissions);
        data_set($webBlock, 'web_block.layout.data.fieldValue', $webpage->website->published_layout['collection']['data']['fieldValue'] ?? []);
        // data_set($webBlock, 'web_block.layout.data.fieldValue.products_route', $productRoute);
        data_set($webBlock, 'web_block.layout.data.fieldValue.collection', WebBlockCollectionResource::make($collection)->toArray(request()));

        return $webBlock;
    }

}
