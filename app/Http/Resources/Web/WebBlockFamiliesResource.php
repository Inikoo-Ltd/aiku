<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 16 May 2025 14:54:33 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Web;

use App\Actions\Helpers\Images\GetPictureSources;
use App\Http\Resources\HasSelfCall;
use App\Models\Helpers\Media;
use Illuminate\Http\Resources\Json\JsonResource;

class WebBlockFamiliesResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {


        $imageSources = null;
        $media        = Media::find($this->image_id);
        if ($media) {
            $width  = 0;
            $height = 0;


            $image        = $media->getImage()->resize($width, $height);
            $imageSources = GetPictureSources::run($image);
        }

        $webImages = json_decode(trim($this->web_images, '"'), true) ?? [];
        return [
            'code'  => $this->code,
            'name'  => $this->name,
            'title' => $this->title,
            'url'   => $this->url,
            'image' => $imageSources,
            'web_images'  => $webImages
        ];
    }
}
