<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 30 May 2025 16:07:56 Central Indonesia Time, Sanur, Shanghai, China
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\WebBlock;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Http\Resources\Web\WebBlockProductResource;
use App\Http\Resources\Web\WebBlockProductResourceEcom;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebBlockProduct
{
    use AsObject;

    public function handle(Webpage $webpage, array $webBlock): array
    {

        $permissions =  [];

        $resourceWebBlockProduct = null;
        if ($webpage->shop->type == ShopTypeEnum::B2B) {
            $resourceWebBlockProduct = WebBlockProductResourceEcom::make($webpage->model)->toArray(request());
        } else {
            $resourceWebBlockProduct = WebBlockProductResource::make($webpage->model)->toArray(request());
        }

        data_set($webBlock, 'web_block.layout.data.permissions', $permissions);
        data_set($webBlock, 'web_block.layout.data.fieldValue', $webpage->website->published_layout['product']['data']['fieldValue'] ?? []);
        data_set($webBlock, 'web_block.layout.data.fieldValue.product', $resourceWebBlockProduct);

        return $webBlock;
    }

}
