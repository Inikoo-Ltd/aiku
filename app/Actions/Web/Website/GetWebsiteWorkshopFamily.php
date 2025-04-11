<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 Jul 2024 15:58:48 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website;

use App\Enums\Web\WebBlockType\WebBlockCategoryScopeEnum;
use App\Http\Resources\Catalogue\FamilyWebsiteResource;
use App\Http\Resources\Catalogue\ProductResource;
use App\Http\Resources\Web\WebBlockTypesResource;
use App\Models\Catalogue\ProductCategory;
use App\Models\Web\WebBlockType;
use App\Models\Web\Website;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebsiteWorkshopFamily
{
    use AsObject;

    public function handle(Website $website, ProductCategory $category): array
    {
        $webBlockTypes = WebBlockType::where('category', WebBlockCategoryScopeEnum::FAMILY->value)->get();

        $webBlockTypes->each(function ($blockType) use ($category) {
            $data = $blockType->data ?? [];
            $fieldValue = $data['fieldValue'] ?? [];

            $fieldValue['product'] = ProductResource::make($category->getProducts()->first());
            $data['fieldValue'] = $fieldValue;
            $blockType->data = $data;
        });

        return [
            'category' => FamilyWebsiteResource::make($category),
            'web_block_types' => WebBlockTypesResource::collection($webBlockTypes)
        ];
    }
}
