<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 15 Nov 2023 17:42:53 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use App\Models\Fulfilment\Fulfilment;
use Lorisleiva\Actions\ActionRequest;

trait WithFulfilmentCustomersSubNavigation
{
    public function getSubNavigation(Fulfilment $fulfilment, ActionRequest $request): array
    {
        $meta = [];

        $meta[] = [
            'route'     => [
                'name'       => 'grp.org.fulfilments.show.crm.customers.index',
                'parameters' => $request->route()->originalParameters()
            ],
            'number'   => $fulfilment->shop->crmStats->number_customers,
            'label'    => __('Customers'),
            'leftIcon' => [
                'icon'    => 'fal fa-user',
                'tooltip' => __('customer')
            ]
        ];

        $meta[] = [
            'route'     => [
                'name'       => 'grp.org.fulfilments.show.crm.customers.pending_approval.index',
                'parameters' => $request->route()->originalParameters()
            ],
            'number'   => $fulfilment->shop->crmStats->number_customers_status_pending_approval,
            'label'    => __('Pending Approval'),
            'leftIcon' => [
                'icon'    => 'fal fa-user-clock',
                'tooltip' => __('pending approval')
            ]
        ];

        $meta[] = [
            'route'     => [
                'name'       => 'grp.org.fulfilments.show.crm.customers.rejected.index',
                'parameters' => $request->route()->originalParameters()
            ],
            'number'   => $fulfilment->shop->crmStats->number_customers_status_rejected,
            'label'    => __('Rejected'),
            'leftIcon' => [
                'icon'    => 'fal fa-user-times',
                'tooltip' => __('rejected')
            ]
        ];

        $meta[] = [
            'route'     => [
                'name'       => 'grp.org.fulfilments.show.crm.web_users.index',
                'parameters' => $request->route()->originalParameters()
            ],
            'align'    => 'right',
            'number'   => $fulfilment->shop->crmStats->number_web_users,
            'label'    => __('Web users'),
            'leftIcon' => [
                'icon'    => 'fal fa-user-circle',
                'tooltip' => __('Website users')
            ]
        ];

        return $meta;
    }
}
