<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 13 Jul 2025 21:45:44 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\CRM;

use App\Actions\Retina\UI\Layout\GetPlatformLogo;
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
 * @property mixed $name
 * @property mixed $status
 * @property mixed $total_amount
 * @property mixed $platform_code
 * @property mixed $platform_name
 * @property mixed $can_connect_to_platform
 * @property mixed $exist_in_platform
 * @property mixed $platform_status
 * @property mixed $number_portfolio_broken
 */
class RetinaCustomerSalesChannelsResource extends JsonResource
{
    use GetPlatformLogo;

    public function toArray($request): array
    {

        $deleteMsg = match ($this->platform_code) {
            'manual' => __('This operation is irreversible.'),
            'shopify' => __('The channel will be unlinked from your Shopify store. You can relinked again creating a new channel.'),
            default => __('The channel will be unlinked from your shop. You can relinked again creating a new channel.'),
        };



        return [
            'slug'                    => $this->slug,
            'id'                      => $this->id,
            'reference'               => $this->reference,
            'name'                    => $this->name ?? $this->reference,
            'number_portfolios'       => $this->number_portfolios,
            'number_portfolio_broken' => $this->number_portfolio_broken,
            'number_customer_clients' => $this->number_customer_clients,
            'number_orders'           => $this->number_orders,
            'type'                    => $this->type,
            'status'                  => $this->status,
            'amount'                  => $this->total_amount,
            'platform_code'           => $this->platform_code,
            'platform_name'           => $this->platform_name,
            'platform_image'          => $this->getPlatformLogo($this->platform_code),
            'can_connect_to_platform' => $this->can_connect_to_platform,
            'exist_in_platform'       => $this->exist_in_platform,
            'platform_status'         => $this->platform_status,

            'delete_msg' => $deleteMsg,

            'delete_route' => [
                'method'     => 'delete',
                'name'       => 'retina.models.customer_sales_channel.delete',
                'parameters' => [
                    'customerSalesChannel' => $this->id
                ]
            ],

        ];
    }
}
