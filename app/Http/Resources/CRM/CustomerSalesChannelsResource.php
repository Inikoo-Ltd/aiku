<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 15 Jul 2025 12:50:38 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\CRM;

use App\Actions\Retina\UI\Layout\GetPlatformLogo;
use App\Actions\Traits\WithPlatformStatusCheck;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $type
 * @property mixed $slug
 * @property mixed $number_portfolios
 * @property mixed $number_orders
 * @property mixed $reference
 * @property mixed $id
 * @property mixed $name
 * @property mixed $status
 * @property mixed $total_amount
 * @property mixed $number_portfolio_broken
 * @property mixed $customer_slug
 * @property mixed $customer_id
 * @property mixed $customer_contact_name
 * @property mixed $platform_type
 * @property mixed $platform_code
 * @property mixed $platform_name
 * @property mixed $can_connect_to_platform
 * @property mixed $exist_in_platform
 * @property mixed $platform_status
 * @property mixed $number_clients
 */
class CustomerSalesChannelsResource extends JsonResource
{
    use GetPlatformLogo;
    use WithPlatformStatusCheck;

    public function toArray($request): array
    {
        return [
            'slug'                    => $this->slug,
            'id'                      => $this->id,
            'reference'               => $this->reference,
            'name'                    => $this->name,
            'number_portfolios'       => $this->number_portfolios,
            'number_portfolio_broken' => $this->number_portfolio_broken,
            'number_clients'          => $this->number_clients,
            'number_orders'           => $this->number_orders,
            'type'                    => $this->type,
            'status'                  => $this->status,
            'total_amount'            => $this->total_amount,
            'platform_type'           => $this->platform_type,
            'platform_code'           => $this->platform_code,
            'platform_name'           => $this->platform_name,
            'platform_image'          => $this->getPlatformLogo($this->platform_code),
            'can_connect_to_platform' => $this->can_connect_to_platform,
            'exist_in_platform'       => $this->exist_in_platform,
            'platform_status'         => $this->platform_status,
            'customer_company_name'   => $this->customer_company_name ?? $this->customer_contact_name,
            'customer_slug'           => $this->customer_slug,
            'customer_id'             => $this->customer_id,


        ];
    }


}
