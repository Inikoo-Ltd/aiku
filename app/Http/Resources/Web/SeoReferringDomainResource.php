<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Http\Resources\Web;

use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $referring_domain
 * @property int|null $rank
 * @property int $backlinks
 * @property bool $is_own_website
 * @property \Illuminate\Support\Carbon|null $first_seen
 * @property \Illuminate\Support\Carbon $first_fetched_at
 * @property \Illuminate\Support\Carbon|null $lost_at
 * @property bool|null $is_new
 * @property int $visits
 * @property float $customers
 * @property float $orders
 * @property float $revenue
 * @property string $currency_code
 */
class SeoReferringDomainResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        return [
            'id'               => $this->id,
            'referring_domain' => $this->referring_domain,
            'rank'             => $this->rank,
            'backlinks'        => (int) $this->backlinks,
            'visits'           => (int) $this->visits,
            'customers'        => round((float) $this->customers, 2),
            'orders'           => round((float) $this->orders, 2),
            'revenue'          => round((float) $this->revenue, 2),
            'currency_code'    => $this->currency_code,
            'is_own_website'   => $this->is_own_website,
            'first_seen'       => $this->first_seen?->toDateString(),
            'lost_at'          => $this->lost_at?->toDateString(),
            'is_new'           => (bool) $this->is_new,
        ];
    }
}
