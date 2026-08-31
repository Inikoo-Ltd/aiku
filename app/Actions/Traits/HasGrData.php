<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 07 Mar 2026 00:44:36 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
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
            'is_gift_opted_out'     => (bool) Arr::get($customer->settings, 'is_gift_opted_out', false),
            'route_gift_opt_out'    => $customer->shop->type !== ShopTypeEnum::EXTERNAL ? [
                'name'       => 'grp.models.customer.update',
                'parameters' => [
                    'customer' => $customer->id,
                ],
            ] : null,
        ];

        if (Arr::get($this->shop->offers_data, 'gr.active')) {
            $grData['shop_has_gr'] = true;

            $lastDaysSinceLastInvoiced = $customer->last_invoiced_at ? -now()->diffInDays($customer->last_invoiced_at) : null;

            $grInterval = Arr::get($this->shop->offers_data, 'gr.interval', 30);

            $grData['gr_extended_until'] = $customer->gr_extended_until?->toDateString();

            if (($lastDaysSinceLastInvoiced ?? 10000) <= $grInterval || $customer->hasActiveGrExtension()) {
                $grData['customer_is_gr'] = true;
                $grData['gr_label']       = Arr::get($this->shop->offers_data, 'gr.label', 'Gold reward member');

                $daysLeft = $grInterval - ($lastDaysSinceLastInvoiced ?? $grInterval);
                if ($customer->hasActiveGrExtension()) {
                    $daysLeft = max($daysLeft, (int) ceil(now()->diffInDays($customer->gr_extended_until->endOfDay())));
                }

                $grData['meter'] = [
                    min($daysLeft, $grInterval),
                    $grInterval,
                ];
            }
            $grData['amnesty']       = Arr::get($this->shop->offers_data, 'gr.amnesty');
            $grData['amnesty_until'] = Arr::get($this->shop->offers_data, 'gr.amnesty_until');
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

            if (($lastDaysSinceLastInvoiced !== null && $lastDaysSinceLastInvoiced <= $grInterval) || $customer->hasActiveGrExtension()) {
                $offerData['type']  = 'gr';
                $offerData['label'] = Arr::get($this->shop->offers_data, 'gr.label', 'Gold reward member');

                $daysLeft = $grInterval - ($lastDaysSinceLastInvoiced ?? $grInterval);
                if ($customer->hasActiveGrExtension()) {
                    $daysLeft = max($daysLeft, (int) ceil(now()->diffInDays($customer->gr_extended_until->endOfDay())));
                }

                $offerData['meter'] = [
                    min($daysLeft, $grInterval),
                    $grInterval,
                ];
            }
            $offerData['amnesty']       = Arr::get($this->shop->offers_data, 'gr.amnesty');
            $offerData['amnesty_until'] = Arr::get($this->shop->offers_data, 'gr.amnesty_until');
        }

        return $offerData;
    }
}
