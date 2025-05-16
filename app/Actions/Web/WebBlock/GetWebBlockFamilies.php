<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 16 May 2025 14:17:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\WebBlock;

use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Http\Resources\Web\WebBlockFamiliesResource;
use App\Models\Web\Webpage;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebBlockFamilies
{
    use AsObject;

    public function handle(Webpage $webpage, array $webBlock): array
    {
        $departments = DB::table('product_categories')
            ->leftJoin('webpages', function ($join) {
                $join->on('product_categories.id', '=', 'webpages.model_id')
                    ->where('webpages.model_type', '=', 'ProductCategory');
            })
            ->select(['product_categories.code', 'name', 'image_id', 'url', 'title'])

            ->where('department_id', $webpage->model_id)
            ->where('product_categories.type', ProductCategoryTypeEnum::FAMILY)
            ->where('show_in_website', true)
            ->get();


        data_set($webBlock, 'web_block.layout.data.fieldValue.families', WebBlockFamiliesResource::collection($departments)->toArray(request()));

        return $webBlock;
    }

}
