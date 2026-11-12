<?php

/*
 * Author: Rifqi Taufiqurrohman
 * Created: Thu, 12 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Web;

use App\Enums\Web\StaticWebsite\StaticWebsiteStateEnum;
use App\Http\Resources\HasSelfCall;
use App\Models\Web\StaticWebsite;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StaticWebsite
 */
class StaticWebsitesResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        $state = $this->state->value;

        return [
            'id'             => $this->id,
            'slug'           => $this->slug,
            'name'           => $this->name,
            'domain'         => $this->domain,
            'url'            => 'https://www.'.$this->domain,
            'preview_url'    => route('grp.marketing.info_websites.preview', [$this->slug]),
            'route'          => ['name' => 'grp.marketing.info_websites.show', 'parameters' => [$this->slug]],
            'webpages_route' => ['name' => 'grp.marketing.info_websites.webpages.index', 'parameters' => [$this->slug]],
            'state'          => $state,
            'state_icon'     => StaticWebsiteStateEnum::stateIcon()[$state],
            'state_label'    => StaticWebsiteStateEnum::labels()[$state],
            'webpages_count' => $this->webpages_count,
            'crawled_at'     => $this->crawled_at,
            'updated_at'     => $this->updated_at,
        ];
    }
}
