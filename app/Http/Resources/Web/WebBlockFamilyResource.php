<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Jun 2025 14:27:36 Central Indonesia Time, Shanghai, China
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Web;

use App\Actions\Helpers\Images\GetPictureSources;
use App\Http\Resources\HasSelfCall;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Helpers\ImageResource;
use App\Models\Helpers\Media;

class WebBlockFamilyResource extends JsonResource
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


        return [
            'slug'  => $this->slug,
            'code'  => $this->code,
            'name'  => $this->name,
            'image' => $imageSources,
            'url'   => $this->url
        ];
    }
}
