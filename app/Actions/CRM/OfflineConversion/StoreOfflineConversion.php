<?php

namespace App\Actions\CRM\OfflineConversion;

use App\Actions\OrgAction;
use App\Models\CRM\Customer;
use App\Models\CRM\CustomerAcquisitionSource;
use App\Models\CRM\OfflineConversion;

class StoreOfflineConversion extends OrgAction
{
    public function handle(Customer $customer, array $modelData): OfflineConversion
    {
        $conversionDate = $modelData['conversion_date'] ?? now();

        $acquisitionSource = null;
        $advertisingPlatform = null;
        $trackingId = null;
        $withinAttributionWindow = false; // the within_attribution_window to know if the conversion is within the attribution window
        $attributionDate = null;

        $acquisitionSource = CustomerAcquisitionSource::where('customer_id', $customer->id)
            ->active()
            ->where('expires_at', '>', $conversionDate)
            ->orderBy('captured_at', 'desc')
            ->first();

        if ($acquisitionSource) {
            $advertisingPlatform = $acquisitionSource->platform;
            $trackingId = $acquisitionSource->tracking_id;
            $withinAttributionWindow = true;
            $attributionDate = $acquisitionSource->captured_at;
        }

        $modelData = array_merge($modelData, [
            'group_id' => $customer->group_id,
            'organisation_id' => $customer->organisation_id,
            'shop_id' => $customer->shop_id,
            'customer_id' => $customer->id,
            'customer_acquisition_source_id' => $acquisitionSource?->id,
            'conversion_date' => $conversionDate,
            'advertising_platform' => $advertisingPlatform,
            'tracking_id' => $trackingId,
            'attribution_date' => $attributionDate,
            'within_attribution_window' => $withinAttributionWindow,
            'upload_status' => OfflineConversion::UPLOAD_STATUS_PENDING,
        ]);

        return OfflineConversion::create($modelData);
    }
}
