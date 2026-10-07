<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product\Traits;

use App\Models\Catalogue\Product;
use App\Models\Masters\MasterAsset;

trait WithPreOrderEditFields
{
    /**
     * @return array<string, mixed>
     */
    protected function preOrderEditFieldsSection(Product|MasterAsset $model, string $masterNote = ''): array
    {
        $integer = ['step' => '1', 'maxFractionDigits' => 0, 'min' => 1];

        return [
            'label'  => __('Pre-order'),
            'icon'   => 'fal fa-hourglass-half',
            'fields' => [
                'is_back_order'                => [
                    'type'        => 'toggle',
                    'label'       => __('Back-order'),
                    'information' => trim(__('A stock item we sell as normal. While it is out of stock customers can still order it, and it is sent when the next delivery arrives.').' '.$masterNote),
                    'value'       => $model->is_back_order,
                ],
                'is_made_to_order'             => [
                    'type'        => 'toggle',
                    'label'       => __('Made-to-order'),
                    'information' => trim(__('Not stocked: we order it from the supplier when a customer buys it. Trade customers pay a deposit at checkout.').' '.$masterNote),
                    'value'       => $model->is_made_to_order,
                ],
                'pre_order_lead_time_days'     => [
                    'type'        => 'input_number',
                    'bind'        => $integer,
                    'label'       => __('Lead time (days)'),
                    'information' => __("Leave empty to use the supplier's pre-order lead time."),
                    'value'       => $model->pre_order_lead_time_days,
                ],
                'pre_order_deposit_percentage' => [
                    'type'        => 'input_number',
                    'bind'        => ['step' => '1', 'maxFractionDigits' => 2, 'min' => 0, 'max' => 100],
                    'label'       => __('Made-to-order deposit (%)'),
                    'information' => __("Leave empty to use the shop's deposit."),
                    'value'       => $model->pre_order_deposit_percentage,
                ],
                'max_quantity_per_order'       => [
                    'type'        => 'input_number',
                    'bind'        => $integer,
                    'label'       => __('Maximum quantity per order'),
                    'information' => __('Leave empty for no limit.'),
                    'value'       => $model->max_quantity_per_order,
                ],
            ],
        ];
    }
}
