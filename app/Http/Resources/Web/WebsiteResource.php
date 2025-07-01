<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 11 Jul 2023 12:17:22 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Web;

use App\Http\Resources\HasSelfCall;
use App\Models\Web\Website;
use Illuminate\Http\Resources\Json\JsonResource;

class WebsiteResource extends JsonResource
{
    use HasSelfCall;
    public function toArray($request): array
    {
        /** @var Website $website */
        $website = $this;
        return [
            'slug'               => $website->slug,
             'id'               => $website->id,
            'code'               => $website->code,
            'name'               => $website->name,
            'domain'             => $website->domain,
            'url'                => app()->environment('local') ? 'http://'.$website->domain.'/' : 'https://'.$website->domain.'/',
            'state'              => $website->state,
            'state_label'        => $website->state->labels()[$website->state->value],
            'state_icon'         => $website->state->stateIcon()[$website->state->value],
            'status'             => $website->status
        ];
    }
}
