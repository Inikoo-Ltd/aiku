<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 Jul 2024 15:58:48 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website;

use App\Enums\Web\WebBlockType\WebBlockCategoryScopeEnum;
use App\Http\Resources\Catalogue\FamilyWebsiteResource;
use App\Http\Resources\Masters\MasterFamiliesResource;
use App\Http\Resources\Web\WebBlockTypesResource;
use App\Models\Catalogue\ProductCategory;
use App\Models\Web\WebBlockType;
use App\Models\Web\Website;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebsiteWorkshopFamily
{
    use AsObject;

    public function handle(Website $website, ProductCategory $productCategory): array
    {
        $webBlockTypes = WebBlockType::where('category', WebBlockCategoryScopeEnum::FAMILY->value)->get();

        $webBlockTypes->each(function ($blockType) use ($website, $productCategory) {
            $data = $blockType->data ?? [];
            $fieldValue = $data['fieldValue'] ?? [];
            $fieldValue['settings'] = Arr::get($website->settings, 'catalogue_template.department');
            $fieldValue['family'] = $this->getFamilies($productCategory);
            $data['fieldValue'] = $fieldValue;
            $blockType->data = $data;
        });

        return [
            'web_block_types' => WebBlockTypesResource::collection($webBlockTypes),
            'website' => $website->settings
        ];
    }

    public function getFamilies(ProductCategory $productCategory): AnonymousResourceCollection
    {
        //        if ($productCategory->follow_master) {
        //            return MasterFamiliesResource::collection($productCategory->masterProductCategory->masterFamilies()->where('show_in_website', true));
        //        }

        return FamilyWebsiteResource::collection($productCategory->getFamilies());
    }
}
