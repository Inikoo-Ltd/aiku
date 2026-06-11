<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Http\Resources\Catalogue;

use App\Enums\Discounts\Offer\OfferStateEnum;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $slug
 * @property string $code
 * @property string $name
 * @property mixed $state
 * @property string $type
 * @property string|null $duration
 * @property mixed $start_at
 * @property mixed $end_at
 * @property string|null $offer_campaign_slug
 * @property string|null $offer_campaign_name
 * @property int $redemptions
 * @property int $redemption_customers
 * @property string $revenue_gross_amount
 * @property string $revenue_net_amount
 * @property string $discounted_amount
 * @property mixed $last_used_at
 */
class OffersInsightsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'slug'                 => $this->slug,
            'code'                 => $this->code,
            'name'                 => $this->name,
            'state'                => OfferStateEnum::stateIcon()[$this->state->value],
            'type'                 => $this->type,
            'duration'             => $this->duration,
            'start_at'             => $this->start_at,
            'end_at'               => $this->end_at,
            'offer_campaign_slug'  => $this->offer_campaign_slug,
            'offer_campaign_name'  => $this->offer_campaign_name,
            'redemptions'          => (int) $this->redemptions,
            'redemption_customers' => (int) $this->redemption_customers,
            'revenue_gross_amount' => $this->revenue_gross_amount,
            'revenue_net_amount'   => $this->revenue_net_amount,
            'discounted_amount'    => $this->discounted_amount,
            'avg_discount'         => $this->redemptions > 0 ? round((float) $this->discounted_amount / (int) $this->redemptions, 2) : 0,
            'last_used_at'         => $this->last_used_at,
        ];
    }
}
