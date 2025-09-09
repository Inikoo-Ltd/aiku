<?php

namespace App\Actions\Catalogue\Product\UI;

use App\Actions\Goods\TradeUnit\IndexTradeUnitImages;
use App\Http\Resources\Helpers\ImageResource;
use App\Http\Resources\Helpers\ImagesResource;
use App\Http\Resources\Helpers\TradeUnitImagesResource;
use App\Models\Catalogue\Product;
use App\Models\Goods\TradeUnit;
use Lorisleiva\Actions\Concerns\AsObject;

class GetProductImagesShowcase
{
    use AsObject;

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

    public function getImagesData(Product $product): array
    {

        return [
            [
                'label' => __('Main'),
                'type'  => 'image',
                'column_in_db' => 'image_id',
                'images' => $product->imageSources(),
            ],
            [
                'label' => __('Video'),
                'type'  => 'video',
                'information' => __('You can use YouTube or Vimeo links'),
                'column_in_db' => 'video_url',
                'url' => $product->video_url,
            ],
            [
                'label' => __('Front side'),
                'type'  => 'image',
                'column_in_db' => 'front_image_id',
                'images' => $product->imageSources(getImage:'frontImage'),
            ],
            [
                'label' => __('Left side'),
                'type'  => 'image',
                'column_in_db' => 'left_image_id',
                'images' => $product->imageSources(getImage:'leftImage'),
            ],
            [
                'label' => __('3/4 angle side'),
                'type'  => 'image',
                'column_in_db' => '34_image_id',
                'images' => $product->imageSources(getImage:'threeQuarterImage'),
            ],
            [
                'label' => __('Right side'),
                'type'  => 'image',
                'column_in_db' => 'right_image_id',
                'images' => $product->imageSources(getImage:'rightImage'),
            ],
            [
                'label' => __('Back side'),
                'type'  => 'image',
                'column_in_db' => 'back_image_id',
                'images' => $product->imageSources(getImage:'backImage'),
            ],
            [
                'label' => __('Top side'),
                'type'  => 'image',
                'column_in_db' => 'top_image_id',
                'images' => $product->imageSources(getImage:'topImage'),
            ],
            [
                'label' => __('Bottom side'),
                'type'  => 'image',
                'column_in_db' => 'bottom_image_id',
                'images' => $product->imageSources(getImage:'bottomImage'),
            ],
            [
                'label' => __('Comparison image'),
                'type'  => 'image',
                'column_in_db' => 'size_comparison_image_id',
                'images' => $product->imageSources(getImage:'sizeComparisonImage'),
            ],
        ];


    }

}
