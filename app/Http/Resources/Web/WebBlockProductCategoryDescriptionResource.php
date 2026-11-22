<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 Jul 2024 16:42:55 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Web;

use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Helpers\WebsiteLanguage;

/**
 * @property string $slug
 * @property string $code
 * @property string $name
 * @property int $image_id
 */
class WebBlockProductCategoryDescriptionResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {

        return [
            'id'                => $this->id,
            'slug'              => $this->slug,
            'code'              => $this->code,
            'name'              => WebsiteLanguage::text($this->resource, 'name'),
            'description'       => WebsiteLanguage::text($this->resource, 'description'),
            'description_title' => WebsiteLanguage::text($this->resource, 'description_title'),
            'description_extra' => WebsiteLanguage::text($this->resource, 'description_extra'),
            'offers_data'       => $this->offers_data,
        ];
    }
}
