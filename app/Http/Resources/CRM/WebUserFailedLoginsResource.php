<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 16 Sep 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\CRM;

use App\Models\CRM\WebUserFailedLogin;
use Illuminate\Http\Resources\Json\JsonResource;

class WebUserFailedLoginsResource extends JsonResource
{
    public static $wrap = null;

    public function toArray($request): array
    {
        /** @var WebUserFailedLogin $webUserFailedLogin */
        $webUserFailedLogin = $this;

        return [
            'id'         => $webUserFailedLogin->id,
            'failed_at'  => $webUserFailedLogin->failed_at,
            'username'   => $webUserFailedLogin->username,
            'source'     => match ($webUserFailedLogin->source) {
                'A'     => __('Login form'),
                'G'     => __('Google'),
                default => $webUserFailedLogin->source,
            },
            'ip_address' => $webUserFailedLogin->ip_address,
            'browser'    => $webUserFailedLogin->browser,
            'os'         => $webUserFailedLogin->os,
            'device'     => $webUserFailedLogin->device,
            'location'   => $this->formatLocation($webUserFailedLogin->location),
        ];
    }

    private function formatLocation(?array $location): ?string
    {
        if (!$location) {
            return null;
        }

        $city    = $location['city'] ?? null;
        $country = $location['country'] ?? null;

        return implode(', ', array_filter([$city, $country])) ?: null;
    }
}
