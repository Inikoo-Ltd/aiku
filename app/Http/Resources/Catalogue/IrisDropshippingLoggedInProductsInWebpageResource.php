<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 Jul 2024 15:08:19 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Catalogue;

use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Http\Resources\HasSelfCall;
use App\Http\Resources\Helpers\ImageResource;
use App\Models\Catalogue\Product;
use App\Models\CRM\Customer;
use App\Models\CRM\Favourite;
use App\Models\Helpers\Media;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $slug
 * @property mixed $image_id
 * @property mixed $code
 * @property mixed $name
 * @property mixed $available_quantity
 * @property mixed $price
 * @property mixed $state
 * @property mixed $created_at
 * @property mixed $updated_at
 * @property mixed $units
 * @property mixed $unit
 * @property mixed $status
 * @property mixed $rrp
 * @property mixed $currency_code
 * @property mixed $id
 * @property mixed $url
 */
class IrisDropshippingLoggedInProductsInWebpageResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        $media = null;
        if ($this->image_id) {
            $media = Media::find($this->image_id);
        }

        $customer = $request->user()->customer;

        $portfolioChannelIds = $customer->portfolios()->where('item_id', $this->id)
            ->where('item_type', class_basename(Product::class))
            ->distinct()
            ->pluck('customer_sales_channel_id')
            ->toArray();

        /** @var Favourite $favourite */
        $favourite = $customer->favourites()->where('product_id', $this->id)->first();

        return [
            'id'                          => $this->id,
            'slug'                        => $this->slug,
            'image_id'                    => $this->image_id,
            'code'                        => $this->code,
            'name'                        => $this->name,
            'stock'                       => $this->available_quantity,
            'price'                       => $this->price,
            'state'                       => $this->state,
            'created_at'                  => $this->created_at,
            'updated_at'                  => $this->updated_at,
            'units'                       => $this->units,
            'unit'                        => $this->unit,
            'url'                         => $this->url,
            'status'                      => $this->status,
            'rrp'                         => $this->rrp,
            'image'                       => $this->image_id ? ImageResource::make($media)->getArray() : null,
            'exist_in_portfolios_channel' => $portfolioChannelIds,
            'is_exist_in_all_channel'     => $this->checkExistInAllChannels($customer),
            'is_favourite'                => $favourite && !$favourite->unfavourited_at,
        ];
    }

    public function checkExistInAllChannels(Customer $customer): bool
    {
        $openChannelIds = $customer->customerSalesChannels()
            ->where('status', CustomerSalesChannelStatusEnum::OPEN)
            ->pluck('id');

        if ($openChannelIds->isEmpty()) {
            return false;
        }

        $portfolioChannels = $customer->portfolios()->where('item_id', $this->id)
            ->where('item_type', class_basename(Product::class))
            ->whereIn('customer_sales_channel_id', $openChannelIds)
            ->distinct('customer_sales_channel_id')
            ->count();

        return $portfolioChannels === $openChannelIds->count();
    }
}
