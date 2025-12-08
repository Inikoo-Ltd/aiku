<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 Jun 2023 20:42:55 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\UI;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $slug
 */
class WebsitesSlugResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'slug' => $this->slug,
        ];
    }
}
