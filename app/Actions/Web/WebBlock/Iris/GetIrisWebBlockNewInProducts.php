<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 09 Nov 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\WebBlock\Iris;

use App\Actions\Catalogue\Product\Json\GetIrisNewInProducts;
use App\Enums\Web\Webpage\WebpageSubTypeEnum;
use App\Http\Resources\Catalogue\IrisProductsInWebpageResource;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\Concerns\AsObject;

class GetIrisWebBlockNewInProducts
{
    use AsObject;

    public const string MODEL_TYPE = 'NewIn';

    public function handle(Webpage $webpage, array $webBlock): array
    {
        data_set($webBlock, 'web_block.layout.data.fieldValue.products', IrisProductsInWebpageResource::collection(GetIrisNewInProducts::run(shop: $webpage->shop, stockMode: 'in_stock')));
        data_set($webBlock, 'web_block.layout.data.fieldValue.products_out_of_stock', IrisProductsInWebpageResource::collection(GetIrisNewInProducts::run(shop: $webpage->shop, stockMode: 'out_of_stock')));
        data_set($webBlock, 'web_block.layout.data.fieldValue.sub_type', WebpageSubTypeEnum::NEW_IN_PAGE->value);
        data_set($webBlock, 'web_block.layout.data.fieldValue.model_type', self::MODEL_TYPE);
        data_set($webBlock, 'web_block.layout.data.fieldValue.model_id', $webpage->id);
        data_set($webBlock, 'web_block.layout.data.fieldValue.model_slug', $webpage->slug);

        return [
            'type'      => data_get($webBlock, 'type'),
            'structure' => data_get($webBlock, 'web_block.layout.data.fieldValue', []),
        ];
    }
}
