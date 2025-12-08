<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 12-11-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\SupplyChain\Agent;

use App\Models\SupplyChain\Agent;

trait WithAgentSubNavigation
{
    protected function getAgentNavigation(Agent $parent): array
    {
        return [
            [
                'isAnchor' => true,
                'label' => __($parent->slug),

                'route' => [
                    'name' => 'grp.supply-chain.agents.show',
                    'parameters' => [$parent->slug],
                ],
                'leftIcon' => [
                    'icon' => ['fal', 'fa-people-arrows'],
                    'tooltip' => __('Agent'),
                ],
            ],
            [
                'number' => $parent->stats->number_suppliers,
                'label' => __('Suppliers'),
                'route' => [
                    'name' => 'grp.supply-chain.agents.show.suppliers.index',
                    'parameters' => [$parent->slug],
                ],
                'leftIcon' => [
                    'icon' => ['fal', 'fa-person-dolly'],
                    'tooltip' => __('Suppliers'),
                ],
            ],
            [
                'number' => $parent->stats->number_supplier_products,
                'label' => __('Products'),
                'route' => [
                    'name' => 'grp.supply-chain.agents.show.supplier_products.index',
                    'parameters' => [$parent],
                ],
                'leftIcon' => [
                    'icon' => ['fal', 'fa-box-usd'],
                    'tooltip' => __('Products'),
                ],
            ],

        ];
    }
}
