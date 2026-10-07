<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 06 Aug 2024 10:14:20 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStockFamily\UI;

use App\Actions\Procurement\GetOrganisationStockCoverBuckets;
use App\Enums\Inventory\OrgStockFamily\OrgStockFamilyStateEnum;
use App\Models\Inventory\OrgStockFamily;
use Lorisleiva\Actions\Concerns\AsObject;

class GetOrgStockFamilyShowcase
{
    use AsObject;

    private const BUCKET_ICONS = [
        'out'    => 'fal fa-times-circle',
        'w1'     => 'fal fa-skull-crossbones',
        'w2'     => 'fal fa-skull-crossbones',
        'w3'     => 'fal fa-exclamation-triangle',
        'w4'     => 'fal fa-exclamation-triangle',
        'ok'     => 'fal fa-dot-circle',
        'excess' => 'fal fa-arrow-alt-circle-up',
        'dead'   => 'fal fa-arrow-alt-circle-up',
    ];

    private const BUCKET_CLASSES = [
        'out'    => 'text-red-700',
        'w1'     => 'text-red-500',
        'w2'     => 'text-orange-500',
        'w3'     => 'text-amber-500',
        'w4'     => 'text-yellow-500',
        'ok'     => 'text-green-500',
        'excess' => 'text-blue-500',
        'dead'   => 'text-gray-500',
    ];

    public function handle(OrgStockFamily $orgStockFamily): array
    {
        $stats       = $orgStockFamily->stats;
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
            'quantity_status' => collect(GetOrganisationStockCoverBuckets::run($orgStockFamily->organisation, $orgStockFamily))
                ->map(fn (array $bucket) => [
                    'label' => $bucket['label'],
                    'count' => $bucket['count'],
                    'icon'  => self::BUCKET_ICONS[$bucket['bucket']],
                    'class' => self::BUCKET_CLASSES[$bucket['bucket']],
                ])->all(),
        ];
    }
}
