<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 19-11-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\Procurement\OrgAgent\Search;

use App\Models\Procurement\OrgAgent;
use Lorisleiva\Actions\Concerns\AsAction;

class OrgAgentRecordSearch
{
    use AsAction;

    public string $jobQueue = 'universal-search';

    public function handle(OrgAgent $orgAgent): void
    {

        $agent = $orgAgent->agent;

        $modelData = [
            'group_id' => $orgAgent->group_id,
            'organisation_id' => $orgAgent->organisation_id,
            'organisation_slug' => $orgAgent->organisation->slug,
            'sections' => ['procurement'],
            'haystack_tier_1' => trim($agent->code.' '.$agent->name),
            'result' => [
                'route' => [
                    'name' => 'grp.org.procurement.org_agents.show',
                    'parameters' => [
                        $orgAgent->organisation->slug,
                        $orgAgent->slug,
                    ],
                ],
                'description' => [
                    'label' => $agent->name,
                ],
                'code' => [
                    'label' => $agent->code,
                ],
                'icon' => [
                    'icon' => 'fal fa-people-arrows',
                ],
                'meta' => [
                    [
                        'type' => 'location',
                        'location' => $agent->organisation->location,
                        'tooltip' => __('Location'),
                    ],
                    [
                        'type' => 'number',
                        'number' => $orgAgent->stats->number_purchase_orders,
                        'label' => __('Purchase orders').': ',
                    ],
                    [
                        'type' => 'number',
                        'number' => $orgAgent->stats->number_org_suppliers,
                        'label' => __('Suppliers').': ',
                    ],
                    [
                        'type' => 'number',
                        'number' => $orgAgent->stats->number_org_supplier_products,
                        'label' => __('Supplier products').': ',
                    ],
                ],
            ],
        ];

        $orgAgent->universalSearch()->updateOrCreate(
            [],
            $modelData
        );

    }
}
