<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 02 Apr 2024 14:30:26 Central Indonesia Time, Bali Office , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\CRM;

use App\Http\Resources\HasSelfCall;
use App\Models\CRM\Customer;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $number_current_customer_clients
 */
class PortfolioResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        /** @var \App\Models\Dropshipping\Portfolio $portfolio */
        $portfolio = $this;

        return [
            'reference'              => $portfolio->reference,
            'product_name'           => $portfolio->item->name,
            'product_code'           => $portfolio->item->code,
            'slug'                   => $portfolio->item->slug,
            'type'                   => $portfolio->type,
            'created_at'             => $portfolio->created_at,
            'routes'                 => [
                'delete_route' => [
                    'method'     => 'delete',
                    'name'       => 'grp.models.portfolio.delete',
                    'parameters' => [
                        'portfolio'    => $portfolio->id
                    ]
                ]
            ]

        ];
    }
}
