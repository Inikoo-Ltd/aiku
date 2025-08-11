<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 15 Jul 2025 12:50:38 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\CRM;

use App\Actions\Retina\UI\Layout\GetPlatformLogo;
use App\Actions\Traits\WithPlatformStatusCheck;
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
 * @property mixed $number_portfolio_broken
 */
class CustomerSalesChannelsResource extends JsonResource
{
    use GetPlatformLogo;
    use WithPlatformStatusCheck;

    public function toArray($request): array
    {
        $customerSalesChannels = CustomerSalesChannel::find($this->id);


        return [
            'slug'                    => $this->slug,
            'id'                      => $this->id,
            'reference'               => $this->reference,
            'name'                    => $this->name,
            'number_portfolios'       => $this->number_portfolios,
            'number_portfolio_broken' => $this->number_portfolio_broken,
            'number_clients'          => $this->number_customer_clients,
            'number_customer_clients' => $this->number_customer_clients,
            'number_orders'           => $this->number_orders,
            'type'                    => $this->type,
            'status'                  => $this->status,
            'total_amount'            => $this->total_amount,
            'platform_code'           => $customerSalesChannels->platform->code,
            'platform_name'           => $customerSalesChannels->platform->name,
            'platform_image'          => $this->getPlatformLogo($customerSalesChannels->platform->code),

            'can_connect_to_platform' => $customerSalesChannels->can_connect_to_platform,
            'exist_in_platform'       => $customerSalesChannels->exist_in_platform,
            'platform_status'         => $customerSalesChannels->platform_status,

            'customer_company_name'   => $this->customer_company_name ?? $this->customer_contact_name,
            'customer_slug'           => $this->customer_slug,
            'customer_id'             => $this->customer_id,


        ];
    }


}
