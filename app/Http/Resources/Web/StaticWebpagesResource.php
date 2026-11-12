<?php

/*
 * Author: Rifqi Taufiqurrohman
 * Created: Thu, 12 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Web;

use App\Http\Resources\HasSelfCall;
use App\Models\Web\StaticWebpage;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StaticWebpage
 * @property string $static_website_slug
 * @property int $snapshots_count
 */
class StaticWebpagesResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        return [
            'id'              => $this->id,
            'path'            => $this->path,
            'title'           => $this->title,
            'snapshots_count' => $this->snapshots_count,
            'updated_at'      => $this->updated_at,
            'route'           => [
                'name'       => 'grp.marketing.info_websites.webpages.show',
                'parameters' => [$this->static_website_slug, $this->id],
            ],
            'preview_url'     => route('grp.marketing.info_websites.preview', [$this->static_website_slug, ltrim($this->path, '/')]),
        ];
    }
}
