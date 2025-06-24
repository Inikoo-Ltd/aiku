<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Jun 2025 14:14:45 Central Indonesia Time, Sanur, Shanghai, China
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Web;

use App\Actions\Helpers\Images\GetPictureSources;
use App\Http\Resources\HasSelfCall;
use App\Models\Helpers\Media;
use Illuminate\Http\Resources\Json\JsonResource;

class WebBlockSubDepartmentsResource extends JsonResource
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

        $decoded = $this->web_images;

        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);

            if (is_string($decoded)) {
                $decoded = json_decode($decoded, true); 
            }
        }


        return [
            'slug'  => $this->slug,
            'code'  => $this->code,
            'name'  => $this->name,
            'image' => $imageSources,
            'url'   => $this->url,
            'web_images' =>  $decoded,
        ];
    }
}
