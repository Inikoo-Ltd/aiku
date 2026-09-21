<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 16 Sep 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\CRM;

use App\Models\CRM\WebUserLogin;
use Illuminate\Http\Resources\Json\JsonResource;

class WebUserLoginsResource extends JsonResource
{
    public static $wrap = null;

    public function toArray($request): array
    {
        /** @var WebUserLogin $webUserLogin */
        $webUserLogin = $this;

        return [
            'id'         => $webUserLogin->id,
            'date'       => $webUserLogin->date,
            'source'     => match ($webUserLogin->source) {
                'A'     => __('Login form'),
                'G'     => __('Google'),
                default => $webUserLogin->source,
            },
            'ip_address' => $webUserLogin->ip_address,
            'browser'    => $webUserLogin->browser,
            'os'         => $webUserLogin->os,
            'device'     => $webUserLogin->device,
            'location'   => $this->formatLocation($webUserLogin->location),
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
