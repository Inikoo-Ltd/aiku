<?php

namespace App\Http\Resources\CRM;

use App\Actions\Helpers\Images\GetPictureSources;
use App\Http\Resources\Catalogue\ProductResource;
use App\Models\Bundle;
use App\Models\BundleItem;
use App\Models\Catalogue\Product;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Media;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $slug
 * @property string $code
 * @property string $name*@property mixed $id
 * @property mixed $media
 * @property mixed $price
 * @property mixed $id
 * @property mixed $gross_weight
 * @property mixed $currency_code
 * @property mixed $currency_id
 * @property mixed $web_images
 * @property mixed $available_quantity
 * @property mixed $rrp
 * @property mixed $stock
 */
class BundleResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var Bundle $bundle */
        $bundle = $this;

        /** @var Product $product */
        $product = $bundle->bundleable;

        $imageArrCurrent = $product->images->mapWithKeys(function (Media $media) {
            return [
                $media->id => GetPictureSources::run($media->getImage()->resize(0, 600))
            ];
        });

        $imageArrList = $product->tradeUnits->flatMap(function (TradeUnit $tradeUnit) {
            return $tradeUnit->images->mapWithKeys(function (Media $media) {
                return [
                    $media->id => GetPictureSources::run($media->getImage()->resize(0, 600))
                ];
            });
        });

        $productList = $bundle->items->map(function (BundleItem $item) {
            return [
                'bundle_item_id' => $item->id,
                'quantity' => $item->quantity,
                'item' => ProductResource::make($item->item)
            ];
        });

        return [
            'id'                 => $bundle->id,
            'bundleable_id'      => $bundle->bundleable_id,
            'bundleable_type'    => $bundle->bundleable_type,
            'slug'               => $product->slug,
            'code'               => $product->code,
            'current_images'     => $imageArrCurrent,
            'list_images'        => $imageArrList,
            'items'              => $productList,
            'price'              => $product->price,
            'name'               => $product->name,
            'gross_weight'       => $product->gross_weight,
            'available_quantity' => $product->available_quantity,
            'rrp'                => $product->rrp
        ];
    }
}
