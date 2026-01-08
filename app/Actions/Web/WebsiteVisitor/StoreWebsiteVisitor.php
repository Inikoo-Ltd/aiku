<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Web\WebsiteVisitor;

use App\Actions\SysAdmin\WithLogRequest;
use App\Models\CRM\WebUser;
use App\Models\Web\Website;
use App\Models\Web\WebsiteVisitor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Stevebauman\Location\Facades\Location;

class StoreWebsiteVisitor
{
    use AsAction;
    use WithLogRequest;

    protected function getLocationWithFallback(string $ip): array
    {
        $cacheKey = "location:ip:$ip";

        return Cache::remember($cacheKey, 86400, function () use ($ip) {
            try {
                $position = Location::get($ip);

                if ($position) {
                    $countryCode = $position->countryCode;
                    $city = $position->cityName;

                    if ($countryCode && $city) {
                        return [
                            'country_code' => $countryCode,
                            'city' => $city,
                        ];
                    }

                    if ($countryCode && !$city) {
                        Log::info('Location found but city missing', [
                            'ip' => $ip,
                            'country' => $countryCode,
                            'region' => $position->regionName ?? null,
                        ]);

                        return [
                            'country_code' => $countryCode,
                            'city' => $position->regionName ?? $position->countryName,
                        ];
                    }
                }

                Log::warning('Location service returned no data', ['ip' => $ip]);
            } catch (\Exception $e) {
                Log::error('Location detection failed', [
                    'ip' => $ip,
                    'error' => $e->getMessage(),
                ]);
            }

            return [
                'country_code' => null,
                'city' => null,
            ];
        });
    }

    public function handle(
        Website $website,
        string $sessionId,
        ?WebUser $webUser,
        array $ips,
        string $device,
        string $browser,
        string $os,
        string $userAgent,
        string $currentUrl,
        ?string $referrer,
    ): WebsiteVisitor {
        $countryCode = null;
        $city = null;
        $selectedIp = $ips[0] ?? '127.0.0.1';

        foreach (array_reverse($ips) as $ip) {
            if (in_array($ip, ['127.0.0.1', '::1', 'localhost'])) {
                continue;
            }

            $locationData = $this->getLocationWithFallback($ip);

            if ($locationData['country_code']) {
                $countryCode = $locationData['country_code'];
                $city = $locationData['city'];
                $selectedIp = $ip;
                break;
            }
        }

        $ipHash = hash('sha256', $selectedIp . config('app.key'));
        $visitorHash = hash('sha256', $ipHash . $userAgent);

        $isNewVisitor = !WebsiteVisitor::where('visitor_hash', $visitorHash)
            ->where('website_id', $website->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->exists();

        $now = now();

        $visitor = WebsiteVisitor::create([
            'group_id'         => $website->group_id,
            'organisation_id'  => $website->organisation_id,
            'shop_id'          => $website->shop_id,
            'website_id'       => $website->id,
            'session_id'       => $sessionId,
            'web_user_id'      => $webUser?->id,
            'visitor_hash'     => $visitorHash,
            'device_type'      => $device,
            'os'               => $os,
            'browser'          => $browser,
            'user_agent'       => $userAgent,
            'ip_hash'          => $ipHash,
            'country_code'     => $countryCode,
            'city'             => $city,
            'page_views'       => 1,
            'duration_seconds' => 0,
            'first_seen_at'    => $now,
            'last_seen_at'     => $now,
            'landing_page'     => $currentUrl,
            'exit_page'        => $currentUrl,
            'referrer_url'     => $referrer,
            'is_bounce'        => true,
            'is_new_visitor'   => $isNewVisitor,
        ]);

        $cacheKey = "visitor:session:$sessionId:$website->id";
        Cache::put($cacheKey, $visitor, 1800);

        return $visitor;
    }
}
