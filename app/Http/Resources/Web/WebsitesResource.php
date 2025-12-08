<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 04 Jun 2024 18:44:37 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Web;

use App\Http\Resources\HasSelfCall;
use App\Models\Catalogue\Shop;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $slug
 * @property mixed $code
 * @property mixed $name
 * @property mixed $domain
 * @property mixed $state
 * @property mixed $status
 * @property mixed $shop_type
 * @property mixed $shop_slug
 * @property mixed $shop_id
 */
class WebsitesResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        $fulfilment_slug = null;
        if ($this->shop_type === 'fulfilment') {
            $shop = Shop::find($this->shop_id);
            $fulfilment_slug = $shop->fulfilment->slug;
        }

        return [
            'slug' => $this->slug,
            'code' => $this->code,
            'name' => $this->name,
            'domain' => $this->domain,
            'url' => app()->environment('local') ? 'http://'.$this->domain.'/' : 'https://'.$this->domain.'/',
            'state' => $this->state,
            'state_label' => $this->state->labels()[$this->state->value],
            'state_icon' => $this->state->stateIcon()[$this->state->value],
            'status' => $this->status,
            'shop_type' => $this->shop_type,
            'shop_slug' => $this->shop_slug,
            'shop_name' => $this->shop_name,
            'organisation_name' => $this->organisation_name,
            'organisation_slug' => $this->organisation_slug,
            'routeUniqueVisitor' => [
                'name' => 'grp.json.website.unique-visitors',
                'parameters' => [$this->resource->slug],
            ],
            'fulfilment_slug' => $fulfilment_slug,

        ];
    }
}
