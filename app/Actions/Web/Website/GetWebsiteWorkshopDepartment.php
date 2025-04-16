<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 Jul 2024 16:11:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website;

use App\Enums\Web\WebBlockType\WebBlockCategoryScopeEnum;
use App\Http\Resources\Catalogue\DepartmentWebsiteResource;
use App\Http\Resources\Web\WebBlockTypesResource;
use App\Models\Catalogue\ProductCategory;
use App\Models\Web\WebBlockType;
use App\Models\Web\Website;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebsiteWorkshopDepartment
{
    use AsObject;

    public function handle(Website $website, ProductCategory $departments): array
    {
        $webBlockTypes = WebBlockType::where('category', WebBlockCategoryScopeEnum::DEPARTMENT->value)->get();

        $webBlockTypes->each(function ($blockType) use ($departments) {
            $data = $blockType->data ?? [];
            $fieldValue = $data['fieldValue'] ?? [];

            $fieldValue['departments'] = DepartmentWebsiteResource::collection($departments);
            $data['fieldValue'] = $fieldValue;
            $blockType->data = $data;
        });

        return [
            'web_block_types' => WebBlockTypesResource::collection($webBlockTypes)
        ];
    }
}
