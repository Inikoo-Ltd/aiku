<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 07 Mar 2026 00:44:36 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use App\Models\CRM\Customer;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

trait HasGrData
{
    protected function getGrData(Customer $customer): array
    {
        $grData = [
            'shop_has_gr'           => false,
            'shop_has_gr_armistice' => false,
            'customer_is_gr'        => false,
        ];

        if (Arr::get($this->shop->offers_data, 'gr.active')) {
            $grData['shop_has_gr'] = true;

            $lastDaysSinceLastInvoiced = $customer->last_invoiced_at ? -now()->diffInDays($customer->last_invoiced_at) : null;

            $grInterval = Arr::get($this->shop->offers_data, 'gr.interval', 30);

            if ($lastDaysSinceLastInvoiced ?? 10000 <= $grInterval) {
                $grData['customer_is_gr'] = true;
                $grData['gr_label']       = Arr::get($this->shop->offers_data, 'gr.label', 'Gold reward member');
                $grData['meter']          = [
                    $grInterval - $lastDaysSinceLastInvoiced,
                    $grInterval,
                ];
            }
        }

        return $grData;
    }

    protected function getGrOfferData(Customer $customer): ?array
    {
        $offerData = null;

        if (Arr::get($this->shop->offers_data, 'gr.active')) {
            $lastDaysSinceLastInvoiced = Cache::remember('customer_days_since_last_invoiced_at_'.$customer->id, now()->addMinutes(15), function () use ($customer) {
                return $customer->last_invoiced_at ? -now()->diffInDays($customer->last_invoiced_at) : null;
            });

            $grInterval = Arr::get($this->shop->offers_data, 'gr.interval', 30);

            if ($lastDaysSinceLastInvoiced !== null && $lastDaysSinceLastInvoiced <= $grInterval) {
                $offerData['type']  = 'gr';
                $offerData['label'] = Arr::get($this->shop->offers_data, 'gr.label', 'Gold reward member');
                $offerData['meter'] = [
                    $grInterval - $lastDaysSinceLastInvoiced,
                    $grInterval,
                ];
            }
        }

        return $offerData;
    }
}
