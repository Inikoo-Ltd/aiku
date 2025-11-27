<?php

/*
 * Author: Vika Aqordi
 * Created on 06-11-2025-14h-01m
 * Github: https://github.com/aqordeon
 * Copyright: 2025
*/

namespace App\Actions\Web\WebBlock;

use App\Models\Catalogue\Product;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebBlockLuigiRecommendations
{
    use AsObject;

    public function handle(Webpage $webpage, array $webBlock): array
    {
        if ($webpage->model instanceof Product) {
            data_set($webBlock, 'web_block.layout.data.fieldValue.product.luigi_identity', $webpage->model->getLuigiIdentity());
        }

        return $webBlock;
    }

}
