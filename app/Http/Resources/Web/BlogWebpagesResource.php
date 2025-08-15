<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 11 Jun 2024 14:30:36 Central European Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Web;

use App\Enums\Web\Website\WebsiteTypeEnum;
use App\Http\Resources\HasSelfCall;
use App\Models\Web\Website;
use Illuminate\Http\Resources\Json\JsonResource;

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
class BlogWebpagesResource extends JsonResource
{
    use HasSelfCall;


    public function toArray($request): array
    {
        $href = '';

        if (app()->isProduction()) {
            $href = 'https://www.';
            if (str_starts_with($this->website->domain, 'www.')) {
                $href .= substr($this->website->domain, 4);
            } else {
                $href .= $this->website->domain;
            }
        } else {
            $website = $request->get('website') ?? Website::find($this->website_id);
            if($website == WebsiteTypeEnum::DROPSHIPPING) {
                $href = 'https://ds.test';
            }
            elseif ($website->type == WebsiteTypeEnum::FULFILMENT) {
                $href = 'https://fulfilment.test';
            }
        }

        $href .= '/blog/' . $this->url;

        $publishedLayout = is_array($this->published_layout) 
        ? $this->published_layout 
        : json_decode($this->published_layout);

        return [
            "id"       => $this->id,
            "slug"     => $this->slug,
            "level"    => $this->level,
            "code"     => $this->code,
            "url"      => $this->url,
            "title"    => $this->title,
            "href"              => $href,
            "type"              => $this->type,
            "typeIcon"          => $this->type->stateIcon()[$this->type->value] ?? ["fal", "fa-browser"],
            "sub_type"          => $this->sub_type,
            "created_at"        => $this->created_at,
            "updated_at"        => $this->updated_at,
            "state"             => $this->state,
            'organisation_name' => $this->organisation_name,
            'organisation_slug' => $this->organisation_slug,
            'shop_name'         => $this->shop_name,
            'shop_slug'         => $this->shop_slug,
            'published_layout' => $publishedLayout,
            'published_at'      => $this->snapshots()->latest()->first()->published_at,
        ];
    }
}
