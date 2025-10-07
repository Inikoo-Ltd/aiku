<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 15 Nov 2023 17:42:53 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use App\Models\Catalogue\Shop;
use Lorisleiva\Actions\ActionRequest;

trait WithProspectsSubNavigation
{
    public function getSubNavigation(Shop $shop, ActionRequest $request): array
    {

        $meta = [];

        $meta[] = [
            'route'     => [
                'name'       => 'grp.org.shops.show.crm.prospects.index',
                'parameters' => array_merge(
                    $request->route()->originalParameters(),
                    [
                        '_query' => [
                            'tab' => 'prospects'
                        ]
                    ]
                )
            ],
            'number'   => $shop->crmStats->number_prospects,
            'label'    => __('Prospects'),
            'leftIcon' => [
                'icon'    => 'fal fa-transporter',
                'tooltip' => __('Prospects')
            ]
        ];

        if ($shop->crmStats->number_prospects > 0) {
            $meta[] = [
                'route'     => [
                    'name'       => 'grp.org.shops.show.crm.prospects.mailshots.index',
                    'parameters' => $request->route()->originalParameters()
                ],
                'number'   => $shop->commsStats->number_mailshots_type_prospect_mailshot,
                'label'    => __('Mailshots'),
                'leftIcon' => [
                    'icon'    => 'fal fa-mail-bulk',
                    'tooltip' => __('Mailshots')
                ]
            ];
        }

        $meta[] = [
            // 'route'     => [
            //     'name'       => 'grp.org.shops.show.crm.prospects.lists.index',
            //     'parameters' => $request->route()->originalParameters()
            // ],
            'number'   => $shop->crmStats->number_prospect_queries,
            'label'    => __('Lists'),
            'leftIcon' => [
                'icon'    => 'fal fa-code-branch',
                'tooltip' => __('lists')
            ]
        ];



        return $meta;
    }
}
