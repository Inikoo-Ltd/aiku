<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Fri, 20 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Http\Resources\CRM;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $slug
 * @property string $reference
 * @property mixed $submitted_at
 * @property mixed $sales
 * @property mixed $profit
 * @property mixed $margin
 * @property mixed $is_estimated
 * @property ?string $campaign
 * @property ?string $customer_name
 * @property ?string $customer_slug
 */
class GoogleAdsOrdersResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'              => $this->id,
            'reference'       => $this->reference,
            'submitted_at'    => $this->submitted_at ? Carbon::parse($this->submitted_at)->toIso8601String() : null,
            'customer_name'   => $this->customer_name,
            'campaign'        => $this->campaign,
            'sales'           => (float) $this->sales,
            'profit'          => $this->profit !== null ? (float) $this->profit : null,
            'margin'          => $this->margin !== null ? (float) $this->margin : null,
            'is_estimated'    => (bool) $this->is_estimated,
            'route'           => [
                'name'       => 'grp.org.shops.show.ordering.orders.show',
                'parameters' => [
                    'organisation' => request()->route()->originalParameter('organisation'),
                    'shop'         => request()->route()->originalParameter('shop'),
                    'order'        => $this->slug,
                ],
            ],
        ];
    }
}
