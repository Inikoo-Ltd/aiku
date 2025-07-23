<?php

namespace App\Actions\CRM\CustomerTrafficAd;

use App\Actions\OrgAction;
use App\Enums\CRM\CustomerTrafficAd\AdvertisingPlatformEnum;
use App\Models\CRM\Customer;
use App\Models\CRM\CustomerTrafficAd;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StoreCustomerTrafficAd extends OrgAction
{
    public function handle(Customer $customer, array $modelData): CustomerTrafficAd
    {
        $platform = $modelData['platform'];
        $capturedAt = $modelData['captured_at'] ?? now();

        // Calculate expiration based on platform attribution window
        // $attributionDays = AdvertisingPlatformEnum::from($platform)->attributionWindowDays();
        // $expiresAt = $capturedAt->copy()->addDays($attributionDays);

        // Deactivate any existing active sources for the same platform
        CustomerTrafficAd::where('customer_id', $customer->id)
            ->where('platform', $platform)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        $modelData = array_merge($modelData, [
            'group_id' => $customer->group_id,
            'organisation_id' => $customer->organisation_id,
            'shop_id' => $customer->shop_id,
            'customer_id' => $customer->id,
            'captured_at' => $capturedAt,
            // 'expires_at' => $expiresAt,
            // 'attribution_window_days' => $attributionDays,
            'is_active' => true,
        ]);

        return CustomerTrafficAd::create($modelData);
    }

    public function rules(): array
    {
        return [
            // 'platform' => ['required', Rule::enum(AdvertisingPlatformEnum::class)],
            'tracking_id' => ['nullable', 'string', 'max:255'],
            'full_url' => ['nullable', 'string', 'max:2048'],
            'captured_at' => ['nullable', 'date'],
            'data' => ['nullable', 'array'],
        ];
    }

    public function action(Customer $customer, array $modelData, bool $strict = true): CustomerTrafficAd
    {
        $this->asAction = true;
        $this->strict = $strict;
        $this->initialisation($customer->organisation, $modelData);

        return $this->handle($customer, $this->validatedData);
    }

    /**
     * Extract tracking data from HTTP request and store acquisition source
     */
    public static function fromRequest(Customer $customer, Request $request): ?CustomerTrafficAd
    {
        $trackingData = self::extractTrackingDataFromRequest($request);

        if (empty($trackingData)) {
            return null;
        }

        return self::make()->action($customer, $trackingData);
    }

    /**
     * Extract advertising tracking parameters from HTTP request
     */
    public static function extractTrackingDataFromRequest(Request $request): array
    {
        $trackingData = [];
        // $query = $request->query();

        return $trackingData;
    }
}
