<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 11 Jul 2023 12:17:22 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Web;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $slug
 * @property string $name
 * @property string $code
 * @property string $domain
 */
class WebsiteSearchResultResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'subtitle' => $this->code,
            'title' => $this->name,
            'label1' => $this->domain,
            'route' => [
                'name' => 'grp.org.shops.show.web.websites.show',
                'parameters' => [
                    'organisation' => $this->organisation->slug,
                    'shop' => $this->shop->slug,
                    'website' => $this->slug,
                ],
            ],
            'icon' => ['fal', 'fa-globe'],

        ];
    }
}
