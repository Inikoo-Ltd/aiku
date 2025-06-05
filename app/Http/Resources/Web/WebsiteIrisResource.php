<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 18 Feb 2024 07:18:56 Central Standard Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Web;

use App\Http\Resources\HasSelfCall;
use App\Models\Web\Website;
use Illuminate\Http\Resources\Json\JsonResource;

class WebsiteIrisResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        /** @var Website $website */
        $website = $this;
        return [
            'id'     => $website->id,
            'slug'   => $website->slug,
            'code'   => $website->code,
            'name'   => $website->name,
            'domain' => $website->domain,
            'url'    => app()->environment('local') ? 'http://'.$website->domain : 'https://'.$website->domain,
            'logo'   => $website->imageSources(350, 350),
            'type'  => $website->shop->type,
        ];
    }
}
