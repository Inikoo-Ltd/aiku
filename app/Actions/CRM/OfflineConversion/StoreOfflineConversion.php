<?php

namespace App\Actions\CRM\OfflineConversion;

use App\Actions\OrgAction;
use App\Enums\CRM\CustomerAcquisitionSource\AdvertisingPlatformEnum;
use App\Models\CRM\Customer;
use App\Models\CRM\CustomerAcquisitionSource;
use App\Models\CRM\OfflineConversion;
use App\Models\Ordering\Order;
use Illuminate\Validation\Rule;

class StoreOfflineConversion extends OrgAction
{
    public function handle(Customer $customer, array $modelData): OfflineConversion
    {
        $conversionDate = $modelData['conversion_date'] ?? now();

        // Find matching acquisition source within attribution window
        $acquisitionSource = null;
        $advertisingPlatform = null;
        $trackingId = null;
        $withinAttributionWindow = false;
        $attributionDate = null;

        // If external order (Shopify/WooCommerce), try to match with customer acquisition sources
        if (!isset($modelData['order_id'])) {
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
        }

        $modelData = array_merge($modelData, [
            'group_id' => $customer->group_id,
            'organisation_id' => $customer->organisation_id,
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

    public function rules(): array
    {
        return [
            'order_id' => ['nullable', 'exists:orders,id'],
            'external_order_id' => ['nullable', 'string', 'max:255'],
            'platform_source' => ['nullable', 'string', 'max:255'],
            'revenue' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'grp_exchange' => ['nullable', 'numeric'],
            'org_exchange' => ['nullable', 'numeric'],
            'conversion_date' => ['nullable', 'date'],
            'data' => ['nullable', 'array'],
        ];
    }

    public function action(Customer $customer, array $modelData, bool $strict = true): OfflineConversion
    {
        $this->asAction = true;
        $this->strict = $strict;
        $this->initialisation($customer->organisation, $modelData);

        return $this->handle($customer, $this->validatedData);
    }

    /**
     * Create offline conversion from internal order
     */
    public static function fromOrder(Order $order): OfflineConversion
    {
        return self::make()->action($order->customer, [
            'order_id' => $order->id,
            'platform_source' => 'internal',
            'revenue' => $order->total_amount ?? $order->net_amount,
            'currency' => $order->currency->code ?? 'USD',
            'grp_exchange' => $order->grp_exchange,
            'org_exchange' => $order->org_exchange,
            'conversion_date' => $order->submitted_at ?? $order->created_at,
        ]);
    }

    /**
     * Create offline conversion from external platform (Shopify, WooCommerce, etc.)
     */
    public static function fromExternalOrder(
        Customer $customer,
        string $externalOrderId,
        string $platformSource,
        float $revenue,
        string $currency = 'USD',
        ?\Carbon\Carbon $conversionDate = null,
        array $additionalData = []
    ): OfflineConversion {
        return self::make()->action($customer, array_merge([
            'external_order_id' => $externalOrderId,
            'platform_source' => $platformSource,
            'revenue' => $revenue,
            'currency' => $currency,
            'conversion_date' => $conversionDate ?? now(),
        ], $additionalData));
    }
}
