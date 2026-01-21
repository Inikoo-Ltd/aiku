<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Jun 2025 14:27:36 Central Indonesia Time, Shanghai, China
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Web;

use App\Http\Resources\Catalogue\OfferResource;
use App\Http\Resources\HasSelfCall;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Http\Resources\Json\JsonResource;

class WebBlockFamilyResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        /** @var ProductCategory $family */
        $family = $this;


        return [
            'slug'              => $family->slug,
            'code'              => $family->code,
            'name'              => $family->name,
            'description'       => $family->description,
            'description_title' => $family->description_title,
            'description_extra' => $family->description_extra,
            'id'                => $family->id,
            'image'             => $family->web_images['main']['original'],
            'url'               => $family->webpage->url,
            'active_offers'     => OfferResource::collection($family->getActiveOffers)->resolve(),
            'offers_data'       => $family->offers_data,
        ];
    }
}
