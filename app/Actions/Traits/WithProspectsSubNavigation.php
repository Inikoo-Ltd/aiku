<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 15 Nov 2023 17:42:53 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use App\Models\Fulfilment\Fulfilment;
use Lorisleiva\Actions\ActionRequest;

trait WithProspectsSubNavigation
{
    public function getSubNavigation(ActionRequest $request): array
    {
        $parent = $this->parent;

        if ($parent instanceof Fulfilment) {
            $parent = $parent->shop;
        }


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
            'number'   => $parent->crmStats->number_prospects,
            'label'    => __('Prospects'),
            'leftIcon' => [
                'icon'    => 'fal fa-transporter',
                'tooltip' => __('prospects')
            ]
        ];

        if ($parent->crmStats->number_prospects > 0) {
            $meta[] = [
                'route'     => [
                    'name'       => 'grp.org.shops.show.crm.prospects.mailshots.index',
                    'parameters' => $request->route()->originalParameters()
                ],
                'number'   => $parent->commsStats->number_mailshots_type_prospect_mailshot,
                'label'    => __('Mailshots'),
                'leftIcon' => [
                    'icon'    => 'fal fa-mail-bulk',
                    'tooltip' => __('mailshots')
                ]
            ];
        }

        $meta[] = [
            // 'route'     => [
            //     'name'       => 'grp.org.shops.show.crm.prospects.lists.index',
            //     'parameters' => $request->route()->originalParameters()
            // ],
            'number'   => $parent->crmStats->number_prospect_queries,
            'label'    => __('Lists'),
            'leftIcon' => [
                'icon'    => 'fal fa-code-branch',
                'tooltip' => __('lists')
            ]
        ];



        return $meta;
    }
}
