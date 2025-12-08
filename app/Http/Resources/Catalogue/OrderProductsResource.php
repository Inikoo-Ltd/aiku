<?php

/*
 * author Arya Permana - Kirin
 * created on 06-12-2024-11h-09m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Http\Resources\Catalogue;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

/**
 * @property string $slug
 * @property string $code
 * @property string $name
 * @property mixed $state
 * @property string $shop_slug
 * @property mixed $shop_code
 * @property mixed $shop_name
 * @property mixed $department_slug
 * @property mixed $department_code
 * @property mixed $department_name
 * @property mixed $family_slug
 * @property mixed $family_code
 * @property mixed $family_name
 * @property mixed $id
 * @property mixed $asset_id
 * @property mixed $current_historic_asset_id
 * @property mixed $available_quantity
 * @property mixed $quantity_ordered
 * @property mixed $transaction_id
 * @property mixed $order_id
 * @property mixed $price
 * @property mixed $web_images
 */
class OrderProductsResource extends JsonResource
{
    public function toArray($request): array
    {
        $webImages = $this->web_images;
        $imageThumbnail = Arr::get($webImages, 'main.thumbnail');

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'asset_id' => $this->asset_id,
            'historic_id' => $this->current_historic_asset_id,
            'code' => $this->code,
            'name' => $this->name,
            'image_thumbnail' => $imageThumbnail,
            'state' => $this->state,
            'available_quantity' => $this->available_quantity,
            'quantity_ordered' => $this->quantity_ordered ?? 0,
            'transaction_id' => $this->transaction_id ?? null,
            'order_id' => $this->order_id ?? null,
            'price' => $this->price,
            'deleteRoute' => [
                'name' => 'grp.models.transaction.delete',
                'parameters' => [
                    'transaction' => $this->transaction_id,
                ],
                'method' => 'delete',
            ],
            'updateRoute' => [
                'name' => 'grp.models.transaction.update',
                'parameters' => [
                    'transaction' => $this->transaction_id,
                ],
                'method' => 'patch',
            ],
        ];
    }
}
