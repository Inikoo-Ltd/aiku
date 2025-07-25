<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Apr 2025 13:26:22 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\CRM;

use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $reference
 * @property mixed $item_name
 * @property mixed $item_code
 * @property mixed $item_slug
 * @property mixed $type
 * @property mixed $created_at
 * @property mixed $id
 * @property mixed $item_type
 * @property mixed $item_id
 * @property mixed $customer_sales_channel_platform_status
 * @property mixed $has_valid_platform_product_id
 * @property mixed $exist_in_platform
 * @property mixed $platform_status
 * @property mixed $platform_possible_matches
 * @property mixed $platform_product_id
 * @property mixed $customer_sales_channel_id
 */
class PortfoliosResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'reference'  => $this->reference,
            'item_name'  => $this->item_name,
            'item_code'  => $this->item_code,
            'item_type'  => $this->item_type,
            'item_id'    => $this->item_id,
            'type'       => $this->type,
            'created_at' => $this->created_at,


            'customer_sales_channel_platform_status' => $this->customer_sales_channel_platform_status,

            'has_valid_platform_product_id' => $this->has_valid_platform_product_id,
            'exist_in_platform'             => $this->exist_in_platform,
            'platform_status'               => $this->platform_status,
            'platform_possible_matches'     => $this->platform_possible_matches,
            'platform_product_id'           => $this->platform_product_id,

            // 'matched_product'               => [
            //     'img'    => 'xx',
            //     'label'  => 'yyy'
            // ],  // TODO: Add matched product details if available

            'customer_sales_channel_id' => $this->customer_sales_channel_id ?? null,
            'routes'                    => [
                'delete_route' => [
                    'method'     => 'delete',
                    'name'       => 'grp.models.portfolio.delete',
                    'parameters' => [
                        'portfolio' => $this->id
                    ]
                ]
            ]

        ];
    }
}
