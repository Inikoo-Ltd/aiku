<?php

/*
 * author Arya Permana - Kirin
 * created on 20-12-2024-16h-14m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Traits;

use Lorisleiva\Actions\ActionRequest;

trait WithCustomersSubNavigation
{
    public function getSubNavigation(ActionRequest $request): array
    {
        $meta = [];

        $meta[] = [
            'route'     => [
                'name'       => 'grp.org.shops.show.crm.customers.index',
                'parameters' => array_merge(
                    $request->route()->originalParameters(),
                    [
                        '_query' => [
                            'tab' => 'customers'
                        ]
                    ]
                )
            ],
            'number'   => $this->parent->crmStats->number_customers,
            'label'    => __('Customers'),
            'leftIcon' => [
                'icon'    => 'fal fa-transporter',
                'tooltip' => __('customers')
            ]
        ];




        $meta[] = [
            // 'route'     => [
            //     'name'       => 'grp.org.shops.show.crm.prospects.lists.index',
            //     'parameters' => $request->route()->originalParameters()
            // ],
            'number'   => $this->parent->crmStats->number_prospect_queries,
            'label'    => __('Lists'),
            'leftIcon' => [
                'icon'    => 'fal fa-code-branch',
                'tooltip' => __('lists')
            ]
        ];



        $meta[] = [
            'route'     => [
                'name'       => 'grp.org.shops.show.crm.polls.index',
                'parameters' => $request->route()->originalParameters()
            ],
            'number'   => $this->parent->crmStats->number_polls,
            'label'    => __('Polls'),
            'leftIcon' => [
                'icon'    => 'fal fa-poll',
                'tooltip' => __('polls')
            ]
        ];


        $meta[] = [
            'route'     => [
                'name'       => 'grp.org.shops.show.crm.web_users.index',
                'parameters' => $request->route()->originalParameters()
            ],
            'align'    => 'right',
            'number'   => $this->parent->crmStats->number_web_users,
            'label'    => __('Web users'),
            'leftIcon' => [
                'icon'    => 'fal fa-user-circle',
                'tooltip' => __('Website users')
            ]
        ];

        return $meta;
    }
}
