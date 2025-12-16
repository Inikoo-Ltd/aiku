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
            "contactCard" => AgentResource::make($agent)->getArray(),
            "stats" => [
                [
                    "label" => __("Suppliers"),
                    "count" => $agent->stats->number_active_suppliers,
                    "orther_counts" => [
                        [
                            "icon" => [
                                "icon" => ["fal", "fa-tombstone"],
                                "title" => __("Archived Suppliers"),
                                "class" => "text-gray-500"
                            ],
                            "count" => $agent->stats->number_archived_suppliers,
                        ],
                    ],
                ],

                [
                    "label" => __("Products"),
                    "count" => $agent->stats->number_supplier_products,
                    "orther_counts" => [
                        [
                            "icon" => [
                                "icon" => ["fal", "fa-tombstone"],
                                "title" => __("Archived Products"),
                                "class" => "text-gray-500"
                            ],
                            "count" => $agent->stats->number_archived_products,
                        ],
                    ],
                ],
            ],
        ];
    }
}
