<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 11 Jun 2024 14:30:36 Central European Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Web;

use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

/**
 * @property mixed $id
 * @property mixed $slug
 * @property mixed $level
 * @property mixed $code
 * @property mixed $url
 * @property mixed $type
 * @property mixed $sub_type
 * @property mixed $created_at
 * @property mixed $updated_at
 * @property mixed $state
 * @property mixed $organisation_name
 * @property mixed $shop_name
 * @property mixed $shop_slug
 * @property mixed $organisation_slug
 * @property mixed $website_url
 * @property mixed $title
 * @property mixed $website_slug
 */
class WebpagesResource extends JsonResource
{
    use HasSelfCall;


    public function toArray($request): array
    {
        return [
            "id"       => $this->id,
            "slug"     => $this->slug,
            "level"    => $this->level,
            "code"     => $this->code,
            "url"      => $this->url,
            "title"    => $this->title,
            "workshop" => route('grp.org.shops.show.web.webpages.workshop', [
                'organisation' => $this->organisation_slug,
                'shop'         => $this->shop_slug,
                'website'      => $this->website_slug,
                'webpage'      => $this->slug,
            ]),
            "href"              => $this->canonical_url,
            'canonical_url'     => $this->canonical_url,
            "type"              => $this->type,
            "typeIcon"          => $this->type->stateIcon()[$this->type->value] ?? ["fal", "fa-browser"],
            "path"              => $this->code,

            "sub_type"          => $this->sub_type,
            "created_at"        => $this->created_at,
            "updated_at"        => $this->updated_at,
            "state"             => $this->state,
            "state_icon"        => $this->state?->stateIcon()[$this->state?->value],
            'organisation_name' => $this->organisation_name,
            'organisation_slug' => $this->organisation_slug,
            'shop_name'         => $this->shop_name,
            'shop_slug'         => $this->shop_slug,
            'published_at'      => $this->isoDateFromQuery('published_at'),
            'last_edited_by'    => Arr::get($this->resource->getAttributes(), 'last_edited_by'),
        ];
    }

    /**
     * Read from the attributes the list query selected, as only the blog list selects these.
     */
    private function isoDateFromQuery(string $key): ?string
    {
        $value = Arr::get($this->resource->getAttributes(), $key);

        return $value ? Carbon::parse($value)->toIso8601String() : null;
    }
}
