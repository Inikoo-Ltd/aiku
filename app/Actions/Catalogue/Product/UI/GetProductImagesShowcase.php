<?php

namespace App\Actions\Catalogue\Product\UI;

use App\Actions\Traits\HasBucketImages;
use App\Http\Resources\Helpers\ImagesResource;
use App\Models\Catalogue\Product;
use Lorisleiva\Actions\Concerns\AsObject;

class GetProductImagesShowcase
{
    use AsObject;
    use HasBucketImages;

    public function handle(Product $product): array
    {
        return [
            'id' => $product->id,
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
                'name'       => 'grp.models.org.product.images.delete',
                'parameters' => [
                    'organisation' => $product->organisation_id,
                    'product' => $product->id,
                    'media'   => ''
                ],
            ],
            'images' => ImagesResource::collection(IndexProductImages::run($product))->resolve(),

        ];
    }


}
