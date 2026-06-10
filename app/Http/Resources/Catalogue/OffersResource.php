<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Tue, 18 Apr 2023 15:23:04 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Catalogue;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Enums\Discounts\Offer\OfferStateEnum;
use App\Enums\Discounts\Offer\OfferTypeEnum;

/**
 * @property int $shop_id
 * @property int $offer_campaign_id
 * @property string $slug
 * @property string $code
 * @property string $data
 * @property mixed $created_at
 * @property mixed $updated_at
 * @property string $name
 * @property mixed $shop_slug
 * @property mixed $offer_campaign_slug
 * @property mixed $state
 * @property mixed $type
 * @property mixed $organisation_name
 * @property mixed $organisation_slug
 * @property mixed $shop_name
 * @property mixed $orders
 * @property mixed $invoices
 * @property mixed $sales_grp_currency_external
 * @property mixed $duration
 * @property mixed $start_at
 * @property mixed $end_at
 */
class OffersResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'created_at'                  => $this->created_at,
            'shop_slug'                   => $this->shop_slug,
            'offer_campaign_slug'         => $this->offer_campaign_slug,
            'slug'                        => $this->slug,
            'state'                       => OfferStateEnum::stateIcon()[$this->state->value],
            'code'                        => $this->code,
            'name'                        => $this->name,
            'type_icon'                   => OfferTypeEnum::from($this->type)?->icons(),
            'type'                        => $this->type,
            'organisation_name'           => $this->organisation_name,
            'organisation_slug'           => $this->organisation_slug,
            'shop_name'                   => $this->shop_name,
            'orders'                      => $this->orders,
            'invoices'                    => $this->invoices,
            'sales_grp_currency_external' => $this->sales_grp_currency_external,
            'duration'                    => $this->duration,
            'start_at'                    => $this->start_at,
            'end_at'                      => $this->end_at,
            'is_active'                   => $this->state == OfferStateEnum::ACTIVE,
        ];
    }
}
