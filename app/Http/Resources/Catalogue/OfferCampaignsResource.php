<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Tue, 18 Apr 2023 15:23:04 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Catalogue;

use App\Enums\Discounts\OfferCampaign\OfferCampaignTypeEnum;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $shop_id
 * @property string $slug
 * @property string $code
 * @property string $data
 * @property mixed $created_at
 * @property mixed $updated_at
 * @property string $name
 * @property mixed $type
 * @property mixed $number_current_offers
 * @property mixed $status
 * @property mixed $shop_slug
 * @property mixed $organisation_name
 * @property mixed $organisation_slug
 * @property mixed $shop_name
 */
class OfferCampaignsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'slug'                        => $this->slug,
            'type'                        => OfferCampaignTypeEnum::from($this->type->value)->icons()[$this->type->value],
            'code'                        => $this->code,
            'name'                        => $this->name,
            'number_current_offers'       => $this->number_current_offers,
            'status'                      => $this->status,
            'status_icon'                 => $this->status
                ? [
                    'tooltip' => __('Active'),
                    'icon'    => 'fas fa-play',
                    'class'   => 'text-green-600',
                    'color'   => 'green',
                    'app'     => [
                        'name' => 'play',
                        'type' => 'font-awesome-5'
                    ]
                ]
                : [
                    'tooltip' => __('Finished'),
                    'icon'    => 'fas fa-stop',
                    'class'   => 'text-red-500',
                    'color'   => 'red',
                    'app'     => [
                        'name' => 'stop',
                        'type' => 'font-awesome-5'
                    ]
                ],
            'shop_slug'                   => $this->shop_slug,
            'organisation_name'           => $this->organisation_name,
            'organisation_slug'           => $this->organisation_slug,
            'shop_name'                   => $this->shop_name,
            'orders'                      => $this->orders ?? 0,
            'invoices'                    => $this->invoices ?? 0,
            'sales_grp_currency_external' => $this->sales_grp_currency_external ?? 0,
        ];
    }
}
