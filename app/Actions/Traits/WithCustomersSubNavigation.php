<?php

/*
 * author Arya Permana - Kirin
 * created on 20-12-2024-16h-14m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Traits;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Helpers\Tag\TagScopeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\Platform;
use App\Models\Helpers\Tag;
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
                )
            ],
            'number'   => $this->parent->crmStats->number_customers ?? 0,
            'label'    => __('Customers'),
            'leftIcon' => [
                'icon'    => 'fal fa-transporter',
                'tooltip' => __('customers')
            ]
        ];

        // $meta[] = [
        //     'route'     => [
        //         'name'       => 'grp.org.shops.show.crm.prospects.lists.index',
        //         'parameters' => $request->route()->originalParameters()
        //     ],
        //     'number'   => $this->parent->crmStats->number_prospect_queries,
        //     'label'    => __('Lists'),
        //     'leftIcon' => [
        //         'icon'    => 'fal fa-code-branch',
        //         'tooltip' => __('lists')
        //     ]
        // ];

        $meta[] = [
            'route'     => [
                'name'       => 'grp.org.shops.show.crm.polls.index',
                'parameters' => $request->route()->originalParameters()
            ],
            'number'   => $this->parent->crmStats->number_polls ?? 0,
            'label'    => __('Polls'),
            'leftIcon' => [
                'icon'    => 'fal fa-poll',
                'tooltip' => __('polls')
            ]
        ];

        $meta[] = [
            'route'     => [
                'name'       => 'grp.org.shops.show.crm.traffic_sources.index',
                'parameters' => $request->route()->originalParameters()
            ],
            'number'   => $this->parent->crmStats?->number_traffic_sources ?? 0,
            'label'    => __('Traffic Sources'),
            'leftIcon' => [
                'icon'    => 'fal fa-route',
                'tooltip' => __('traffic sources')
            ]
        ];

        if ($this->parent instanceof Shop && $this->parent->type === ShopTypeEnum::DROPSHIPPING) {
            $meta[] = [
                'route'     => [
                    'name'       => 'grp.org.shops.show.crm.platforms.index',
                    'parameters' => $request->route()->originalParameters()
                ],
                'number'   => Platform::all()->count() ?? 0, // Fix Later with hydrators
                'label'    => __('Platforms'),
                'leftIcon' => [
                    'icon'    => 'fal fa-route',
                    'tooltip' => __('platforms')
                ]
            ];
        }

        $meta[] = [
            'route'     => [
                'name'       => 'grp.org.shops.show.crm.web_users.index',
                'parameters' => $request->route()->originalParameters()
            ],
            'align'    => 'right',
            'number'   => $this->parent->crmStats->number_web_users ?? 0,
            'label'    => __('Web Users'),
            'leftIcon' => [
                'icon'    => 'fal fa-user-circle',
                'tooltip' => __('Website users')
            ]
        ];

        $meta[] = [
            'route'     => [
                'name'       => 'grp.org.shops.show.crm.self_filled_tags.index',
                'parameters' => $request->route()->originalParameters()
            ],
            'number'   => Tag::where('shop_id', $this->parent->id)->where('scope', TagScopeEnum::USER_CUSTOMER)->count() ?? 0,
            'label'    => __('Self-Filled Tags'),
            'leftIcon' => [
                'icon'    => 'fal fa-tags',
                'tooltip' => __('Self-filled tags')
            ]
        ];

        return $meta;
    }
}
