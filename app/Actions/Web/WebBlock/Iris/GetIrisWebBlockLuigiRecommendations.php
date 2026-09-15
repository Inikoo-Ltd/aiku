<?php

/*
 * Author: Vika Aqordi
 * Created on 06-11-2025-14h-01m
 * Github: https://github.com/aqordeon
 * Copyright: 2025
*/

namespace App\Actions\Web\WebBlock\Iris;

use App\Actions\Traits\WithProductRecommendationScope;
use App\Models\Catalogue\Product;
use App\Models\Web\Webpage;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

class GetIrisWebBlockLuigiRecommendations
{
    use AsObject;
    use WithProductRecommendationScope;

    public function handle(Webpage $webpage, array $webBlock): ?array
    {
        if (Arr::get($webBlock, 'type') === 'luigi-trends-1' && $this->isFamilyWebpage($webpage)) {
            return null;
        }

        if ($webpage->model instanceof Product) {
            data_set($webBlock, 'web_block.layout.data.fieldValue.product.id', $webpage->model->id);
            data_set($webBlock, 'web_block.layout.data.fieldValue.product.luigi_identity', $webpage->model->getLuigiIdentity());
        }

        data_set($webBlock, 'web_block.layout.data.fieldValue.recommendation_scope', $this->getRecommendationScope($webpage));

        return $webBlock;
    }
}
