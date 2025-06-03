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
            ->select(['slug', 'code', 'name', 'image_id', 'url'])
            ->where('type', ProductCategoryTypeEnum::SUB_DEPARTMENT)
            ->where('show_in_website', true)
            ->get();




        data_set($webBlock, 'web_block.layout.data.fieldValue.sub_departments', WebBlockSubDepartmentsResource::collection($subDepartments)->toArray(request()));

        return $webBlock;
    }

}
