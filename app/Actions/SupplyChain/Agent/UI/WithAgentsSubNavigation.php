<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\Agent\UI;

trait WithAgentsSubNavigation
{
    protected function getAgentsSubNavigation(): array
    {
        return [
            [
                'label'  => __('Agents'),
                'root'   => 'grp.supply-chain.agents.index',
                'route'  => ['name' => 'grp.supply-chain.agents.index', 'parameters' => []],
                'icon'   => 'fal fa-people-arrows',
            ],
            [
                'label'  => __('Purchase orders'),
                'root'   => 'grp.supply-chain.agents.purchase_order_states',
                'route'  => ['name' => 'grp.supply-chain.agents.purchase_order_states', 'parameters' => []],
                'icon'   => 'fal fa-clipboard-list',
            ],
        ];
    }
}
