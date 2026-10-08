<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Http\Resources\Web;

use App\Enums\Web\WebsiteVisitor\WebsiteVisitorChannelEnum;
use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $slug
 * @property mixed $name
 * @property mixed $contact_name
 * @property mixed $email
 * @property mixed $organisation_slug
 * @property mixed $shop_slug
 * @property mixed $checkouts
 * @property mixed $purchases
 * @property mixed $revenue
 * @property mixed $last_event_at
 * @property mixed $traffic_source_types
 */
class WebsiteConversionCustomerResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'contact_name'  => $this->contact_name,
            'email'         => $this->email,
            'route'         => [
                'name'       => 'grp.org.shops.show.crm.customers.show',
                'parameters' => [$this->organisation_slug, $this->shop_slug, $this->slug],
            ],
            'channels'      => collect(explode(',', (string) $this->traffic_source_types))
                ->filter()
                ->map(fn (string $type) => WebsiteVisitorChannelEnum::typeLabel($type))
                ->values()
                ->all(),
            'checkouts'     => (int) $this->checkouts,
            'purchases'     => (int) $this->purchases,
            'revenue'       => (float) $this->revenue,
            'last_event_at' => $this->last_event_at,
        ];
    }
}
