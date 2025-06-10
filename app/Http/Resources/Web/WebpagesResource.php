<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 11 Jun 2024 14:30:36 Central European Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Web;

use App\Enums\Web\Webpage\WebpageTypeEnum;
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
 */
class WebpagesResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        return [
            "id" => $this->id,
            "slug" => $this->slug,
            "level" => $this->level,
            "code" => $this->code,
            "url" => $this->url,
            "title" => $this->title,
            "workshop" => route('grp.org.shops.show.web.webpages.workshop', [
                'organisation' => $this->organisation_slug,
                'shop' => $this->shop_slug,
                'website' => $this->website_slug,
                'webpage' => $this->slug,
            ]),
            "href" => (app()->isProduction() ? 'https://' : 'http://'). $this->website_url . '/' . $this->url,
            "type" => $this->type,
            "typeIcon" => $this->type->stateIcon()[$this->type->value] ?? ["fal", "fa-browser"],
            /* 	"typeIcon2" => match ($this->type) {
                WebpageTypeEnum::STOREFRONT => ["fal", "fa-home"],
                WebpageTypeEnum::OPERATIONS => ["fal", "fa-sign-in-alt"],
                WebpageTypeEnum::BLOG => ["fal", "fa-newspaper"],
                default => ["fal", "fa-browser"],
            }, */
            "sub_type" => $this->sub_type,
            "created_at" => $this->created_at,
            "updated_at" => $this->updated_at,
            "state" => $this->state,
            'organisation_name' => $this->organisation_name,
            'organisation_slug' => $this->organisation_slug,
            'shop_name'         => $this->shop_name,
            'shop_slug'         => $this->shop_slug,
        ];
    }
}
