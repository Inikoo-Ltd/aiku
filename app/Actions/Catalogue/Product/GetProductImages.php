<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 05 Feb 2026 00:30:59 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product;

use App\Actions\Catalogue\Product\UI\IndexProductImages;
use App\Actions\Traits\HasBucketImages;
use App\Http\Resources\Helpers\ImagesResource;
use App\Models\Catalogue\Product;
use Lorisleiva\Actions\Concerns\AsObject;

class GetProductImages
{
    use AsObject;
    use HasBucketImages;

    public function handle(Product $product): array
    {
        return [
            'id'                  => $product->id,
            'bucket_images'       => $product->bucket_images,
            'images_category_box' => $this->getImagesData($product),
            'images_update_route' => [
                'method'     => 'patch',
                'name'       => 'grp.models.product.update_images',
                'parameters' => [
                    'masterAsset' => $product->id,
                ],
            ],
            'upload_images_route' => [
                'method'     => 'post',
                'name'       => 'grp.models.product.upload_images',
                'parameters' => [
                    'masterAsset' => $product->id,
                ],
            ],
            'delete_images_route' => [
                'method'     => 'post',
                'name'       => 'grp.models.product.delete_images',
                'parameters' => [
                    'masterAsset' => $product->id,
                    'media'       => ''
                ],
            ],
            'images'              => ImagesResource::collection(IndexProductImages::run($product))->resolve(),

        ];
    }
}
