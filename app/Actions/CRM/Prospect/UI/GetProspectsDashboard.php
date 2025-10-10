<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 03 Nov 2023 12:51:29 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Prospect\UI;

use App\Enums\CRM\Prospect\ProspectContactedStateEnum;
use App\Enums\CRM\Prospect\ProspectFailStatusEnum;
use App\Enums\CRM\Prospect\ProspectStateEnum;
use App\Enums\CRM\Prospect\ProspectSuccessStatusEnum;
use App\Enums\UI\CRM\ProspectsTabsEnum;
use App\Models\Catalogue\Shop;
use Lorisleiva\Actions\Concerns\AsObject;

class GetProspectsDashboard
{
    use AsObject;

    public function handle(Shop $parent): array
    {
        $stats = [];


        // $stats['prospects'] = [
        //     'label' => __('Prospects'),
        //     'count' => $parent->crmStats->number_prospects
        // ];
        // foreach (ProspectStateEnum::cases() as $case) {
        //     $stats['prospects']['cases'][$case->value] = [
        //         'value' => $case->value,
        //         'icon'  => ProspectStateEnum::stateIcon()[$case->value],
        //         'count' => ProspectStateEnum::count($parent)[$case->value],
        //         'label' => ProspectStateEnum::labels()[$case->value],
        //         'route' => [
        //             'name' => 'grp.org.shops.show.crm.prospects.index',
        //             'parameters' => [
        //                 'organisation' => $parent->organisation->slug,
        //                 'shop'         => $parent->slug,
        //                 'prospects_elements[state]' => $case->value,
        //                 'tab'          => ProspectsTabsEnum::PROSPECTS->value
        //             ]
        //         ]
        //     ];
        // }

        $stats['opted_in_prospects'] = [
            'label' => __('Opted In Prospects'),
            'count' => $parent->crmStats->number_opt_in_prospects
        ];
        foreach (ProspectStateEnum::cases() as $case) {
            $stats['opted_in_prospects']['cases'][$case->value] = [
                'value' => $case->value,
                'icon'  => ProspectStateEnum::stateIcon()[$case->value],
                'count' => ProspectStateEnum::count($parent, 'opt_in')[$case->value],
                'label' => ProspectStateEnum::labels()[$case->value],
                'route' => [
                    'name' => 'grp.org.shops.show.crm.prospects.index',
                    'parameters' => [
                        'organisation' => $parent->organisation->slug,
                        'shop'         => $parent->slug,
                        'opt_in_elements[state]' => $case->value,
                        'tab'          => ProspectsTabsEnum::OPT_IN->value
                    ]
                ]
            ];
        }

        $stats['opted_out_prospects'] = [
            'label' => __('Opted Out Prospects'),
            'count' => $parent->crmStats->number_opt_out_prospects
        ];
        foreach (ProspectStateEnum::cases() as $case) {
            $stats['opted_out_prospects']['cases'][$case->value] = [
                'value' => $case->value,
                'icon'  => ProspectStateEnum::stateIcon()[$case->value],
                'count' => ProspectStateEnum::count($parent, 'opt_out')[$case->value],
                'label' => ProspectStateEnum::labels()[$case->value],
                'route' => [
                    'name' => 'grp.org.shops.show.crm.prospects.index',
                    'parameters' => [
                        'organisation' => $parent->organisation->slug,
                        'shop'         => $parent->slug,
                        'opt_out_elements[state]' => $case->value,
                        'tab'          => ProspectsTabsEnum::OPT_OUT->value
                    ]
                ]
            ];
        }

        $stats['contacted'] = [
            'label' => __('Contacted'),
            'count' => $parent->crmStats->number_prospects_state_contacted
        ];
        foreach (ProspectContactedStateEnum::cases() as $case) {
            if ($case == ProspectContactedStateEnum::NA) {
                continue;
            }
            $stats['contacted']['cases'][$case->value] = [
                'value' => $case->value,
                'icon'  => ProspectContactedStateEnum::stateIcon()[$case->value],
                'count' => ProspectContactedStateEnum::count($parent)[$case->value],
                'label' => ProspectContactedStateEnum::labels()[$case->value],
                'route' => [
                    'name' => 'grp.org.shops.show.crm.prospects.index',
                    'parameters' => [
                        'organisation' => $parent->organisation->slug,
                        'shop'         => $parent->slug,
                        'contacted_elements[contacted_state]' => $case->value,
                        'tab'          => ProspectsTabsEnum::CONTACTED->value
                    ]
                ]
            ];
        }

        $stats['fail'] = [
            'label' => __('Failed'),
            'count' => $parent->crmStats->number_prospects_state_fail
        ];
        foreach (ProspectFailStatusEnum::cases() as $case) {
            if ($case == ProspectFailStatusEnum::NA) {
                continue;
            }
            $stats['fail']['cases'][$case->value] = [
                'value' => $case->value,
                'icon'  => ProspectFailStatusEnum::statusIcon()[$case->value],
                'count' => ProspectFailStatusEnum::count($parent)[$case->value],
                'label' => ProspectFailStatusEnum::labels()[$case->value],
                'route' => [
                    'name' => 'grp.org.shops.show.crm.prospects.index',
                    'parameters' => [
                        'organisation' => $parent->organisation->slug,
                        'shop'         => $parent->slug,
                        'failed_elements[fail_status]' => $case->value,
                        'tab'          => ProspectsTabsEnum::FAILED->value
                    ]
                ]
            ];
        }

        $stats['success'] = [
            'label' => __('Success'),
            'count' => $parent->crmStats->number_prospects_state_success
        ];
        foreach (ProspectSuccessStatusEnum::cases() as $case) {
            if ($case == ProspectSuccessStatusEnum::NA) {
                continue;
            }
            $stats['success']['cases'][$case->value] = [
                'value' => $case->value,
                'icon'  => ProspectSuccessStatusEnum::statusIcon()[$case->value],
                'count' => ProspectSuccessStatusEnum::count($parent)[$case->value],
                'label' => ProspectSuccessStatusEnum::labels()[$case->value],
                'route' => [
                    'name' => 'grp.org.shops.show.crm.prospects.index',
                    'parameters' => [
                        'organisation' => $parent->organisation->slug,
                        'shop'         => $parent->slug,
                        'success_elements[success_status]' => $case->value,
                        'tab'          => ProspectsTabsEnum::SUCCESS->value
                    ]
                ]
            ];
        }


        return [
            'prospectStats' => $stats,
        ];
    }

}
