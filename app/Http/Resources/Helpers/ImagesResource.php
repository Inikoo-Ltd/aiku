<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 06 Jul 2025 19:19:20 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Helpers;

use App\Actions\Helpers\Images\GetPictureSources;
use App\Helpers\NaturalLanguage;
use App\Http\Resources\HasSelfCall;
use App\Models\Helpers\Media;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $size
 * @property mixed $name
 * @property mixed $id
 * @property mixed $sub_scope
 */
class ImagesResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        $media = Media::find($this->id);


        $image        = $media->getImage()->resize(0, 100);
        $imageSources = GetPictureSources::run($image);


        return [
            'id'        => $this->id,
            'name'      => $this->name,
            'size'      => NaturalLanguage::make()->fileSize($this->size, 1, 'MB'),
            'image'     => $imageSources,
            'sub_scope' => $this->sub_scope,
            'dimensions' => [
                'width'  => $this->width ?? 0,
                'height' => $this->height ?? 0
            ],
        ];
    }
}
