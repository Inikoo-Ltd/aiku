<?php

/*
 * author Arya Permana - Kirin
 * created on 11-04-2025-09h-29m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Resources\CRM;

use App\Models\Dropshipping\Platform;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $type
 * @property mixed $slug
 * @property mixed $number_portfolios
 * @property mixed $number_customer_clients
 * @property mixed $number_orders
 * @property mixed $platform_id
 * @property mixed $reference
 * @property mixed $id
 */
class CustomerSalesChannelsResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var Platform $platform */
        $platform = Platform::find($this->platform_id);

        return [
            'slug'              => $this->slug,
            'id'                => $this->id,
            'reference'         => $this->reference,
            'number_portfolios' => $this->number_portfolios,
            'number_clients'    => $this->number_customer_clients,
            'number_orders'     => $this->number_orders,
            'type'              => $this->type,
            'amount'            => $this->total_amount,
            'platform_image'    => $platform?->imageSources(48, 48) ?? null,
        ];
    }
}
