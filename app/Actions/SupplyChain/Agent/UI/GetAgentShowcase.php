<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 04 May 2024 12:55:09 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\Agent\UI;

use App\Http\Resources\SupplyChain\AgentResource;
use App\Models\SupplyChain\Agent;
use Lorisleiva\Actions\Concerns\AsObject;

class GetAgentShowcase
{
    use AsObject;

    public function handle(Agent $agent): array
    {
        return [
            'contactCard'              => AgentResource::make($agent)->getArray(),
            'stats'                    => [
                [
                    'label' => __('suppliers'),
                    'count' => $agent->stats->number_suppliers
                ],
                [
                    'label' => __('products'),
                    'count' => $agent->stats->number_supplier_products
                ]
            ]
        ];
    }
}
