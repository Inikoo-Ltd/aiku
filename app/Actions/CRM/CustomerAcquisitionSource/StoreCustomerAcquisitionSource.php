<?php

/*
 * Author: Peter Murphy <peter@grp.org>
 * Created: Mon, 15 Jul 2025 10:00:00 GMT
 * Copyright (c) 2025, GRP
 */

namespace App\Actions\CRM\CustomerAcquisitionSource;

use App\Actions\OrgAction;
use App\Enums\CRM\CustomerAcquisitionSource\AdvertisingPlatformEnum;
use App\Models\CRM\Customer;
use App\Models\CRM\CustomerAcquisitionSource;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StoreCustomerAcquisitionSource extends OrgAction
{
    public function handle(Customer $customer, array $modelData): CustomerAcquisitionSource
    {
        $platform = $modelData['platform'];
        $capturedAt = $modelData['captured_at'] ?? now();

        // Calculate expiration based on platform attribution window
        $attributionDays = AdvertisingPlatformEnum::from($platform)->attributionWindowDays();
        $expiresAt = $capturedAt->copy()->addDays($attributionDays);

        // Deactivate any existing active sources for the same platform
        CustomerAcquisitionSource::where('customer_id', $customer->id)
            ->where('platform', $platform)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        $modelData = array_merge($modelData, [
            'group_id' => $customer->group_id,
            'organisation_id' => $customer->organisation_id,
            'shop_id' => $customer->shop_id,
            'customer_id' => $customer->id,
            'captured_at' => $capturedAt,
            'expires_at' => $expiresAt,
            'attribution_window_days' => $attributionDays,
            'is_active' => true,
        ]);

        return CustomerAcquisitionSource::create($modelData);
    }

    public function rules(): array
    {
        return [
            'platform' => ['required', Rule::enum(AdvertisingPlatformEnum::class)],
            'tracking_id' => ['nullable', 'string', 'max:255'],
            'utm_parameters' => ['nullable', 'array'],
            'utm_parameters.utm_source' => ['nullable', 'string', 'max:255'],
            'utm_parameters.utm_medium' => ['nullable', 'string', 'max:255'],
            'utm_parameters.utm_campaign' => ['nullable', 'string', 'max:255'],
            'utm_parameters.utm_term' => ['nullable', 'string', 'max:255'],
            'utm_parameters.utm_content' => ['nullable', 'string', 'max:255'],
            'referrer_url' => ['nullable', 'string', 'max:2048'],
            'landing_page' => ['nullable', 'string', 'max:2048'],
            'ip_address' => ['nullable', 'ip'],
            'user_agent' => ['nullable', 'string', 'max:1024'],
            'captured_at' => ['nullable', 'date'],
            'data' => ['nullable', 'array'],
        ];
    }

    public function action(Customer $customer, array $modelData, bool $strict = true): CustomerAcquisitionSource
    {
        $this->asAction = true;
        $this->strict = $strict;
        $this->initialisation($customer->organisation, $modelData);

        return $this->handle($customer, $this->validatedData);
    }

    /**
     * Extract tracking data from HTTP request and store acquisition source
     */
    public static function fromRequest(Customer $customer, Request $request): ?CustomerAcquisitionSource
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

        // Check for advertising platform click IDs
        $platformTrackingParams = [
            'gclid' => AdvertisingPlatformEnum::GOOGLE_ADS,
            'fbclid' => AdvertisingPlatformEnum::META_ADS,
            '_fbp' => AdvertisingPlatformEnum::META_ADS,
            'msclkid' => AdvertisingPlatformEnum::MICROSOFT_ADS,
            'ttclid' => AdvertisingPlatformEnum::TIKTOK_ADS,
            'li_fat_id' => AdvertisingPlatformEnum::LINKEDIN_ADS,
            'epik' => AdvertisingPlatformEnum::PINTEREST_ADS,
            'ScCid' => AdvertisingPlatformEnum::SNAPCHAT_ADS,
            'twclid' => AdvertisingPlatformEnum::TWITTER_ADS,
            'aclid' => AdvertisingPlatformEnum::AMAZON_ADS,
        ];

        foreach ($platformTrackingParams as $param => $platform) {
            if ($request->has($param) && !empty($request->get($param))) {
                $trackingData['platform'] = $platform->value;
                $trackingData['tracking_id'] = $request->get($param);
                break; // Use first found platform tracking parameter
            }
        }

        // If no platform-specific tracking found, check UTM parameters
        if (empty($trackingData) && $request->has('utm_source')) {
            $utmSource = strtolower($request->get('utm_source', ''));
            $utmMedium = strtolower($request->get('utm_medium', ''));

            // Try to determine platform from UTM parameters
            $platform = self::determinePlatformFromUtm($utmSource, $utmMedium);
            if ($platform) {
                $trackingData['platform'] = $platform->value;
            } else {
                $trackingData['platform'] = AdvertisingPlatformEnum::OTHER->value;
            }
        }

        // Extract UTM parameters if present
        $utmParams = [];
        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'] as $param) {
            if ($request->has($param)) {
                $utmParams[$param] = $request->get($param);
            }
        }
        if (!empty($utmParams)) {
            $trackingData['utm_parameters'] = $utmParams;
        }

        // Add request metadata
        if (!empty($trackingData)) {
            $trackingData['referrer_url'] = $request->header('referer');
            $trackingData['landing_page'] = $request->fullUrl();
            $trackingData['ip_address'] = $request->ip();
            $trackingData['user_agent'] = $request->header('user-agent');
            $trackingData['captured_at'] = now();
        }

        return $trackingData;
    }

    /**
     * Determine advertising platform from UTM parameters
     */
    private static function determinePlatformFromUtm(string $utmSource, string $utmMedium): ?AdvertisingPlatformEnum
    {
        $sourceMapping = [
            'google' => AdvertisingPlatformEnum::GOOGLE_ADS,
            'facebook' => AdvertisingPlatformEnum::META_ADS,
            'instagram' => AdvertisingPlatformEnum::META_ADS,
            'meta' => AdvertisingPlatformEnum::META_ADS,
            'bing' => AdvertisingPlatformEnum::MICROSOFT_ADS,
            'microsoft' => AdvertisingPlatformEnum::MICROSOFT_ADS,
            'tiktok' => AdvertisingPlatformEnum::TIKTOK_ADS,
            'linkedin' => AdvertisingPlatformEnum::LINKEDIN_ADS,
            'pinterest' => AdvertisingPlatformEnum::PINTEREST_ADS,
            'snapchat' => AdvertisingPlatformEnum::SNAPCHAT_ADS,
            'twitter' => AdvertisingPlatformEnum::TWITTER_ADS,
            'amazon' => AdvertisingPlatformEnum::AMAZON_ADS,
            'youtube' => AdvertisingPlatformEnum::YOUTUBE_ADS,
        ];

        $mediumMapping = [
            'cpc' => true, // Cost per click (paid)
            'ppc' => true, // Pay per click
            'paid' => true,
            'social' => AdvertisingPlatformEnum::SOCIAL_ORGANIC,
            'email' => AdvertisingPlatformEnum::EMAIL,
            'organic' => AdvertisingPlatformEnum::ORGANIC_SEARCH,
        ];

        // Check source mapping first
        foreach ($sourceMapping as $source => $platform) {
            if (str_contains($utmSource, $source)) {
                // Only return if medium suggests paid advertising
                if (in_array($utmMedium, ['cpc', 'ppc', 'paid'])) {
                    return $platform;
                }
            }
        }

        // Check medium mapping
        if (isset($mediumMapping[$utmMedium])) {
            $mediumPlatform = $mediumMapping[$utmMedium];
            if ($mediumPlatform instanceof AdvertisingPlatformEnum) {
                return $mediumPlatform;
            }
        }

        return null;
    }
}
