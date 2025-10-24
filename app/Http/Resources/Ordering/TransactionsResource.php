<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 06 Apr 2024 09:40:37 Central Indonesia Time, Bali Office, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Ordering;

use App\Http\Resources\Helpers\ImageResource;
use App\Models\Catalogue\Product;
use App\Models\Helpers\Media;
use App\Models\SysAdmin\User;
use App\Models\Web\Webpage;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $slug
 * @property string $code
 * @property mixed $created_at
 * @property mixed $updated_at
 * @property string $name
 * @property string $state
 * @property string $shop_slug
 * @property string $date
 * @property string $reference
 * @property mixed $id
 * @property mixed $quantity_ordered
 * @property mixed $status
 * @property mixed $quantity_bonus
 * @property mixed $quantity_dispatched
 * @property mixed $quantity_fail
 * @property mixed $quantity_cancelled
 * @property mixed $gross_amount
 * @property mixed $net_amount
 * @property string $asset_code
 * @property string $asset_name
 * @property string $asset_type
 * @property string $product_slug
 * @property string $currency_code
 * @property mixed $order_id
 * @property mixed $price
 */
class TransactionsResource extends JsonResource
{
    public function toArray($request): array
    {
        $media = null;
        if ($this->product_image_id) {
            $media = Media::find($this->product_image_id);
        }

        $webpageUrl = null;
        if ($this->model_type === class_basename(Product::class)) {
            $webpage = Webpage::where('model_id', $this->product_id)
            ->where('model_type', class_basename(Product::class))->first();

            $webpageUrl = $webpage->getUrl();
        }
        
        return [
            'id'                  => $this->id,
            'state'               => $this->state,
            'status'              => $this->status,
            'quantity_ordered'    => $this->quantity_ordered,
            'quantity_bonus'      => $this->quantity_bonus,
            'quantity_dispatched' => $this->quantity_dispatched,
            'quantity_fail'       => $this->quantity_fail,
            'quantity_cancelled'  => $this->quantity_cancelled,
            'gross_amount'        => $this->gross_amount,
            'net_amount'          => $this->net_amount,
            'price'               => $this->price,
            'asset_code'          => $this->asset_code,
            'asset_name'          => $this->asset_name,
            'asset_type'          => $this->asset_type,
            'image'               => $this->product_image_id ? ImageResource::make($media)->getArray() : null,
            'product_slug'        => $this->product_slug,
            'created_at'          => $this->created_at,
            'currency_code'       => $this->currency_code,
            'available_quantity'  => $this->available_quantity ?? 0,
            'webpage_url'         => $webpageUrl,  // this is 'domain/aisb-06', should be 'domain/incense/resin-powders/aisb/aisb-06'

            'deleteRoute' => $request->user() instanceof User
                ? [
                    'name'       => 'grp.models.transaction.delete',
                    'parameters' => [
                        'transaction' => $this->id
                    ],
                    'method'     => 'delete'
                ]
                : [
                    'name'       => 'retina.models.transaction.delete',
                    'parameters' => [
                        'transaction' => $this->id
                    ],
                    'method'     => 'delete'
                ],
            'updateRoute' => $request->user() instanceof User
                ? [
                    'name'       => 'grp.models.transaction.update',
                    'parameters' => [
                        'transaction' => $this->id
                    ],
                    'method'     => 'patch'
                ]
                : [
                    'name'       => 'retina.models.transaction.update',
                    'parameters' => [
                        'transaction' => $this->id
                    ],
                    'method'     => 'patch'
                ],
        ];
    }
}
