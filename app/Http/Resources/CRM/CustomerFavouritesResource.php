<?php

/*
 * author Arya Permana - Kirin
 * created on 14-10-2024-10h-32m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Http\Resources\CRM;

use App\Http\Resources\HasSelfCall;
use App\Http\Resources\Helpers\ImageResource;
use App\Models\Helpers\Media;
use App\Models\Web\Webpage;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $code
 * @property mixed $slug
 * @property mixed $name
 * @property mixed $description
 * @property mixed $price
 */
class CustomerFavouritesResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        $media = null;
        if ($this->image_id) {
            $media = Media::find($this->image_id);
        }

        $webpage = null;
        if ($this->webpage_id) {
            $webpage = Webpage::find($this->webpage_id);
        }

        return [
            'image'         => $this->image_id ? ImageResource::make($media)->getArray() : null,
            'id'                     => $this->id,
            'code'                   => $this->code,
            'slug'                   => $this->slug,
            'name'                   => $this->name,
            'description'            => $this->description,
            'price'                  => $this->price,
            'url'                    => $webpage ? $webpage->getUrl() : null  // This not correct yet
        ];
    }
}
