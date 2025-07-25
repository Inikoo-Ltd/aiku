<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 15 Jul 2025 12:50:38 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\CRM;

use App\Actions\Retina\UI\Layout\GetPlatformLogo;
use App\Actions\Traits\WithPlatformStatusCheck;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
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
 */
class CustomerSalesChannelsResourcePro extends JsonResource
{
    use GetPlatformLogo;
    use WithPlatformStatusCheck;

    public function toArray($request): array
    {

        $customerSalesChannels = CustomerSalesChannel::find($this->id);


        return [
            'slug'                                => $this->slug,
            'id'                                  => $this->id,
            'reference'                           => $this->reference,
            'name'                                => $this->name,
            'number_portfolios'                   => $this->number_portfolios,
            'number_portfolio_broken'             => $this->number_portfolio_broken,
            'number_clients'                      => $this->number_customer_clients,
            'number_customer_clients'             => $this->number_customer_clients,
            'number_orders'                       => $this->number_orders,
            'type'                                => $this->type,
            'status'                              => $this->status,
            'amount'                              => $this->total_amount,
            'platform_code'                       => $customerSalesChannels->platform->code,
            'platform_name'                       => $customerSalesChannels->platform->name,
            'platform_image'                      => $this->getPlatformLogo($customerSalesChannels->platform->code),
            'connection'                          => $this->getStatus($customerSalesChannels),

            'can_connect_to_platform' => $customerSalesChannels->can_connect_to_platform,
            'exist_in_platform' => $customerSalesChannels->exist_in_platform,
            'platform_status' => $customerSalesChannels->platform_status,

            'update_customer_sales_channel_route' => [
                'method'     => 'patch',
                'name'       => 'retina.models.customer_sales_channel.update',
                'parameters' => [
                    'customerSalesChannel' => $customerSalesChannels->id
                ]
            ],
            'reconnect_route'                     => [
                'name'       => 'retina.dropshipping.customer_sales_channels.reconnect',
                'parameters' => [
                    'customerSalesChannel' => $this->slug
                ],
                'method'     => 'get',
            ],
            'unlink_route'                        => [
                'method'     => 'delete',
                'name'       => 'retina.models.customer_sales_channel.unlink',
                'parameters' => [
                    'customerSalesChannel' => $this->id
                ]
            ],
            'toggle_route'                        => [
                'method'     => 'patch',
                'name'       => 'retina.models.customer_sales_channel.toggle',
                'parameters' => [
                    'customerSalesChannel' => $this->id
                ]
            ]
        ];
    }

    public function getStatus(CustomerSalesChannel $customerSalesChannels): string
    {
        $status                = $customerSalesChannels->status->labels()[$customerSalesChannels->status->value];

        if ($customerSalesChannels->platform->type == PlatformTypeEnum::SHOPIFY) {

            if ($customerSalesChannels->status != CustomerSalesChannelStatusEnum::CLOSED &&  !$customerSalesChannels->user) {
                return '⚠️ Fatal Error';
            }

        }

        return $status;
    }

}
