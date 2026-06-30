<?php

/*
 * Author: stewicca <wiccaalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Http\Resources\Web;

use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;

class RestrictedCountryResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        return [
            'country'  => $this->country,
            'city'     => $this->city,
            'postcode' => $this->postcode,
            'ip'       => (string) $this->ip,
        ];
    }
}
