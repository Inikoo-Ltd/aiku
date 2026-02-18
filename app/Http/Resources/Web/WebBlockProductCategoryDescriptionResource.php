<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 Jul 2024 16:42:55 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Web;

use App\Actions\Helpers\Images\GetPictureSources;
use App\Http\Resources\HasSelfCall;
use App\Models\Helpers\Media;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'name'              => $this->name,
            'description'       => $this->description,
            'description_title' => $this->description_title,
            'description_extra' => $this->description_extra,
        ];
    }
}
