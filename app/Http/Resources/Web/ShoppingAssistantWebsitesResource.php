<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Web;

use App\Models\Web\Website;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Website
 */
class ShoppingAssistantWebsitesResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'            => $this->id,
            'domain'        => $this->domain,
            'route'         => [
                'name'       => 'grp.org.shops.show.web.analytics.shopping_assistant.show',
                'parameters' => [$this->organisation_slug, $this->shop_slug, $this->slug],
            ],
            'conversations' => (int) $this->conversations,
            'customers'     => (int) $this->customers,
            'orders'        => (int) $this->orders,
            'revenue'       => (float) $this->revenue,
            'conversion'    => (float) $this->conversion,
            'cost'          => (float) $this->cost,
            'enabled'       => (bool) data_get($this->settings, 'shopping_assistant.enabled'),
            'budget'        => $this->budget,
        ];
    }
}
