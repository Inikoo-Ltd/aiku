<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 12 Jun 2024 12:56:07 Central European Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Api\Dropshipping;

use App\Http\Resources\HasSelfCall;
use App\Models\Web\Website;
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
        /** @var Website $website */
        $website = $this;

        return [
            'id' => $website->id,
            'slug' => $website->slug,
            'code' => $website->code,
            'name' => $website->name,
            'domain' => $website->domain,
            'url' => app()->environment('local') ? 'http://'.$website->domain.'/' : 'https://'.$website->domain.'/',
            'state' => $website->state,
            'status' => $website->status,
            'created_at' => $website->created_at,
            'updated_at' => $website->updated_at,

        ];
    }
}
