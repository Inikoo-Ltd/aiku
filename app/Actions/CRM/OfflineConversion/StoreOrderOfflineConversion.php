<?php

namespace App\Actions\CRM\OfflineConversion;

use App\Actions\OrgAction;
use App\Enums\CRM\OfflineConversion\OfflineConversionUploadStatusEnum;
use App\Models\CRM\CustomerTrafficAd;
use App\Models\CRM\OfflineConversion;
use App\Models\Ordering\Order;

class StoreOrderOfflineConversion extends OrgAction
{
    public function handle(Order $order): void
    {

        if (!$order->platform_order_id) {
            return;
        }

        $conversionDate = $order->submitted_at ?? $order->created_at ?? now();

        $acquisitionSource = CustomerTrafficAd::where('customer_id', $order->customer->id)
            ->active()
            ->where('expires_at', '>', $conversionDate)
            ->orderBy('captured_at', 'desc')
            ->first();

        if (!$acquisitionSource?->exists()) {
            return;
        }

        OfflineConversion::create([
            'group_id' => $order->customer->group_id,
            'organisation_id' => $order->customer->organisation_id,
            'shop_id' => $order->customer->shop_id,
            'customer_id' => $order->customer->id,
            'customer_acquisition_source_id' => $acquisitionSource->id,
            'order_id' => $order->id,
            'platform_source' => $order->platform->name,
            'external_order_id' => $order->platform_order_id,
            'advertising_platform' => $acquisitionSource->platform,
            'revenue' => $order->total_amount ?? $order->net_amount,
            'currency' => $order->currency->code ?? 'USD',
            'grp_exchange' => $order->grp_exchange,
            'org_exchange' => $order->org_exchange,
            'conversion_date' => $conversionDate,
            'tracking_id' => $acquisitionSource->tracking_id,
            'attribution_date' => $acquisitionSource->captured_at,
            'upload_status' => OfflineConversionUploadStatusEnum::PENDING,
        ]);
    }
}
