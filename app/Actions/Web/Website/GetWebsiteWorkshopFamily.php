<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 Jul 2024 15:58:48 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website;

use App\Enums\Web\WebBlockType\WebBlockCategoryScopeEnum;
use App\Http\Resources\Catalogue\FamilyWebsiteResource;
use App\Http\Resources\Web\WebBlockTypesResource;
use App\Models\Web\WebBlockType;
use App\Models\Web\Website;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebsiteWorkshopFamily
{
    use AsObject;

    public function handle(Website $website, Collection $families): array
    {
        $webBlockTypes = WebBlockType::where('category', WebBlockCategoryScopeEnum::FAMILY->value)->get();

        $webBlockTypes->each(function ($blockType) use ($families) {
            $data = $blockType->data ?? [];
            $fieldValue = $data['fieldValue'] ?? [];

            $fieldValue['family'] = FamilyWebsiteResource::collection($families);
            $data['fieldValue'] = $fieldValue;
            $blockType->data = $data;
        });

        return [
            'web_block_types' => WebBlockTypesResource::collection($webBlockTypes)
        ];
    }
}
