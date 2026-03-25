<?php

namespace App\Http\Resources\CRM;

use App\Actions\Helpers\Images\GetPictureSources;
use App\Models\Helpers\Media;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

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
class SelectedProductsForBundleResource extends JsonResource
{
    public function toArray($request): array
    {
        $imageArr = $this->images->mapWithKeys(function (Media $media) {
            return [
                $media->id => GetPictureSources::run($media->getImage()->resize(0, 600))
            ];
        });

        return [
            'id'                 => $this->id,
            'slug'               => $this->slug,
            'code'               => $this->code,
            'image'              => $imageArr,
            'price'              => $this->price,
            'name'               => $this->name,
            'gross_weight'       => $this->gross_weight,
            'available_quantity' => $this->available_quantity,
            'rrp'                => $this->rrp,
            'currency_code'      => $this->currency_code,
            'currency_id'        => $this->currency_id,
            'stock'              => $this->stock
        ];
    }
}
