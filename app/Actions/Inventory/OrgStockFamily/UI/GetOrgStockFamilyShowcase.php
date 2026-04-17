<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 06 Aug 2024 10:14:20 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStockFamily\UI;

use App\Enums\Inventory\OrgStockFamily\OrgStockFamilyStateEnum;
use App\Models\Inventory\OrgStockFamily;
use Lorisleiva\Actions\Concerns\AsObject;

class GetOrgStockFamilyShowcase
{
    use AsObject;

    public function handle(OrgStockFamily $orgStockFamily): array
    {
        $stats       = $orgStockFamily->stats;
        $intervals   = $orgStockFamily->intervals;
        $stockFamily = $orgStockFamily->stockFamily;
        $stateIcon   = OrgStockFamilyStateEnum::stateIcon()[$orgStockFamily->state->value];

        return [
            'family_data'     => [
                'name'        => $orgStockFamily->name,
                'code'        => $orgStockFamily->code,
                'description' => $stockFamily?->description,
                'image'       => $stockFamily?->imageSources(720, 480),
                'state'       => [
                    'label'   => OrgStockFamilyStateEnum::labels()[$orgStockFamily->state->value],
                    'icon'    => $stateIcon['icon'],
                    'class'   => $stateIcon['class'],
                    'tooltip' => $stateIcon['tooltip'],
                ],
            ],
            'stock_counts'    => [
                [
                    'label' => __('Active'),
                    'count' => $stats?->number_org_stocks_state_active ?? 0,
                    'icon'  => 'fal fa-check-circle',
                    'class' => 'text-green-500',
                ],
                [
                    'label' => __('Discontinuing'),
                    'count' => $stats?->number_org_stocks_state_discontinuing ?? 0,
                    'icon'  => 'fal fa-exclamation-circle',
                    'class' => 'text-yellow-500',
                ],
                [
                    'label' => __('Discontinued'),
                    'count' => $stats?->number_org_stocks_state_discontinued ?? 0,
                    'icon'  => 'fal fa-times-circle',
                    'class' => 'text-red-500',
                ],
                [
                    'label' => __('Suspended'),
                    'count' => $stats?->number_org_stocks_state_suspended ?? 0,
                    'icon'  => 'fal fa-pause-circle',
                    'class' => 'text-gray-500',
                ],
            ],
            'quantity_status' => [
                [
                    'label' => __('Ideal'),
                    'count' => $stats?->number_org_stocks_quantity_status_ideal ?? 0,
                    'icon'  => 'fal fa-dot-circle',
                    'class' => 'text-green-500',
                ],
                [
                    'label' => __('Low'),
                    'count' => $stats?->number_org_stocks_quantity_status_low ?? 0,
                    'icon'  => 'fal fa-exclamation-triangle',
                    'class' => 'text-yellow-500',
                ],
                [
                    'label' => __('Critical'),
                    'count' => $stats?->number_org_stocks_quantity_status_critical ?? 0,
                    'icon'  => 'fal fa-skull-crossbones',
                    'class' => 'text-red-500',
                ],
                [
                    'label' => __('Out of stock'),
                    'count' => $stats?->number_org_stocks_quantity_status_out_of_stock ?? 0,
                    'icon'  => 'fal fa-times-circle',
                    'class' => 'text-red-700',
                ],
                [
                    'label' => __('Excess'),
                    'count' => $stats?->number_org_stocks_quantity_status_excess ?? 0,
                    'icon'  => 'fal fa-arrow-alt-circle-up',
                    'class' => 'text-blue-500',
                ],
            ],
            'dispatched'      => [
                'today'      => $intervals?->dispatched_tdy ?? 0,
                'last_week'  => $intervals?->dispatched_lw ?? 0,
                'last_month' => $intervals?->dispatched_lm ?? 0,
                'last_year'  => $intervals?->dispatched_1y ?? 0,
                'all'        => $intervals?->dispatched_all ?? 0,
            ],
        ];
    }
}
