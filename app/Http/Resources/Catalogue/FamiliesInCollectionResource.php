<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Jun 2025 23:32:13 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Catalogue;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Helpers\Media;
use App\Actions\Helpers\Images\GetPictureSources;

/**
 * @property string $slug
 * @property mixed $state
 * @property string $code
 * @property string $name
 * @property string $description
 * @property int $number_current_products
 * @property-read \App\Models\Helpers\Media|null $image
 * @property mixed $image_id
 * @property mixed $id
 */
class FamiliesInCollectionResource extends JsonResource
{
    public function toArray($request): array
    {
        $imageSources = null;
        $media        = Media::find($this->image_id);
        if ($media) {
            $width  = 720;
            $height = 720;


            $image        = $media->getImage()->resize($width, $height);
            $imageSources = GetPictureSources::run($image);
        }


        return [
            'id'   => $this->id,
            'slug' => $this->slug,

            'image'                   => $imageSources,
            'state'                   => [
                'tooltip' => $this->state->labels()[$this->state->value],
                'icon'    => $this->state->stateIcon()[$this->state->value]['icon'],
                'class'   => $this->state->stateIcon()[$this->state->value]['class']
            ],
            'code'                    => $this->code,
            'name'                    => $this->name,
            'description'             => $this->description,
            'number_current_products' => $this->number_current_products,

        ];
    }
}
