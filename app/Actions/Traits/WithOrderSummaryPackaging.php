<?php

/*
 * Author: Andi Ferdiawan
 * Created: Mon, 14 Jul 2026 11:00:00 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Traits;

use App\Models\Ordering\Order;

trait WithOrderSummaryPackaging
{
    /**
     * Builds the "Charges" summary group split into Charges, Packaging, Add-ons and Shipping.
     * Packaging/Add-ons rows only appear when they have a value.
     *
     * @return array<int, array{label: string, information: string, price_total: mixed}>
     */
    protected function buildChargesSummaryGroup(Order $order): array
    {
        $group = [
            [
                'label'       => __('Charges'),
                'information' => '',
                'price_total' => $order->charges_amount,
            ],
        ];

        if ((float) $order->packaging_amount > 0) {
            $group[] = [
                'label'       => __('Packaging'),
                'information' => '',
                'price_total' => $order->packaging_amount,
            ];
        }

        if ((float) $order->leaflet_amount > 0) {
            $group[] = [
                'label'       => __('Add-ons'),
                'information' => '',
                'price_total' => $order->leaflet_amount,
            ];
        }

        $group[] = [
            'label'       => __('Shipping'),
            'information' => '',
            'price_total' => $order->shipping_amount,
        ];

        return $group;
    }
}
