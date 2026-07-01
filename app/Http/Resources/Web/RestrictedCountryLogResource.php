<?php

/*
 * Author: stewicca <wiccaalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Http\Resources\Web;

use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;

class RestrictedCountryLogResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        return [
            'country'         => $this->country,
            'city'            => $this->city,
            'postcode'        => $this->postcode,
            'ip'              => (string) $this->ip,
            'was_blocked'     => (bool) $this->was_blocked,
            'number_requests' => $this->number_requests,
            'last_request_at' => $this->last_request_at,
        ];
    }
}
