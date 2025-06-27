<?php
/*
 * author Arya Permana - Kirin
 * created on 27-06-2025-12h-18m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Resources\Web;

use App\Http\Resources\HasSelfCall;
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
class ProductCategoryWebpagesResource extends JsonResource
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
            "href"     => (app()->isProduction() ? 'https://' : 'http://').$this->website_url.'/'.$this->url,
            "type"     => $this->type,
            "typeIcon" => $this->type->stateIcon()[$this->type->value] ?? ["fal", "fa-browser"],

            "sub_type"          => $this->sub_type,
            "created_at"        => $this->created_at,
            "updated_at"        => $this->updated_at,
            "state"             => $this->state,
            'organisation_name' => $this->organisation_name,
            'organisation_slug' => $this->organisation_slug,
            'shop_name'         => $this->shop_name,
            'shop_slug'         => $this->shop_slug,
            'number_current_families'        => $this->number_current_families ?? 0,
            'number_current_products'        => $this->number_current_products ?? 0,
            'number_current_sub_departments' => $this->number_current_sub_departments ?? 0,
        ];
    }
}
