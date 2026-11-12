<?php

/*
 * Author: Rifqi Taufiqurrohman
 * Created: Thu, 12 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Web;

use App\Http\Resources\HasSelfCall;
use App\Models\Web\StaticWebsiteComponent;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StaticWebsiteComponent
 * @property string $static_website_slug
 * @property int $snapshots_count
 * @property int $webpages_count
 * @property int $size
 */
class StaticWebsiteComponentsResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        return [
            'id'              => $this->id,
            'code'            => $this->code->value,
            'label'           => match ($this->code->value) {
                'header' => __('Header and menu'),
                'footer' => __('Footer'),
                default  => $this->code->value,
            },
            'webpages_count'  => (int) $this->webpages_count,
            'snapshots_count' => $this->snapshots_count,
            'size'            => round($this->size / 1024).' KB',
            'updated_at'      => $this->updated_at,
            'route'           => [
                'name'       => 'grp.marketing.info_websites.components.show',
                'parameters' => [$this->static_website_slug, $this->code->value],
            ],
        ];
    }
}
