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
            'editable'            => $product->not_follow_master_media,
            'images_category_box' => $this->getImagesData($product),
            'images_update_route' => [
                'method'     => 'patch',
                'name'       => 'grp.models.product.images.update_images',
                'parameters' => [
                    'product' => $product->id,
                ],
            ],
            'upload_images_route' => [
                'method'     => 'post',
                'name'       => 'grp.models.product.images.store',
                'parameters' => [
                    'product' => $product->id,
                ],
            ],
            'delete_images_route' => [
                'method'     => 'post',
                'name'       => 'grp.models.product.images.delete_images',
                'parameters' => [
                    'product' => $product->id,
                    'media'       => ''
                ],
            ],
            'update_image_alt_route' => [
                'method'     => 'patch',
                'name'       => 'grp.models.product.images.update_image_alt',
                'parameters' => [
                    'product' => $product->id,
                    'media'   => ''
                ],
            ],
            'images'              => ImagesResource::collection(IndexProductImages::run($product))->resolve(),
        ];
    }
}
