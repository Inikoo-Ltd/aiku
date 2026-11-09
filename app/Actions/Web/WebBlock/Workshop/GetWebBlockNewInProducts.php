<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 09 Nov 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\WebBlock\Workshop;

use App\Actions\Catalogue\Product\Json\GetIrisNewInProducts;
use App\Actions\Web\WebBlock\Iris\GetIrisWebBlockNewInProducts;
use App\Enums\Web\Webpage\WebpageSubTypeEnum;
use App\Http\Resources\Catalogue\IrisProductsInWebpageForWorkshopResource;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebBlockNewInProducts
{
    use AsObject;

    public function handle(Webpage $webpage, array $webBlock): array
    {
        data_set($webBlock, 'web_block.layout.data.permissions', ['edit']);
        data_set($webBlock, 'web_block.layout.data.fieldValue.products', IrisProductsInWebpageForWorkshopResource::collection(GetIrisNewInProducts::run(shop: $webpage->shop, stockMode: 'in_stock')));
        data_set($webBlock, 'web_block.layout.data.fieldValue.products_out_of_stock', IrisProductsInWebpageForWorkshopResource::collection(GetIrisNewInProducts::run(shop: $webpage->shop, stockMode: 'out_of_stock')));
        data_set($webBlock, 'web_block.layout.data.fieldValue.sub_type', WebpageSubTypeEnum::NEW_IN_PAGE->value);
        data_set($webBlock, 'web_block.layout.data.fieldValue.model_type', GetIrisWebBlockNewInProducts::MODEL_TYPE);
        data_set($webBlock, 'web_block.layout.data.fieldValue.model_id', $webpage->id);
        data_set($webBlock, 'web_block.layout.data.fieldValue.model_slug', $webpage->slug);

        return $webBlock;
    }
}
