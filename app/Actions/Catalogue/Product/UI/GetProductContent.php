<?php

namespace App\Actions\Catalogue\Product\UI;

/*
 * Author: Vika Aqordi
 * Created on 17-11-2025-10h-36m
 * Github: https://github.com/aqordeon
 * Copyright: 2025
*/

use App\Actions\Traits\HasBucketImages;
use App\Http\Resources\Catalogue\ProductResource;
use App\Models\Catalogue\Product;
use Lorisleiva\Actions\Concerns\AsObject;
use App\Actions\Traits\HasBucketAttachment;

class GetProductContent
{
    use AsObject;
    use HasBucketImages;
    use HasBucketAttachment;

    public function handle(Product $product): array
    {

        return [
            'product'               => ProductResource::make($product)->toArray(request()),
        ];
    }

}
