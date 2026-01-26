<?php

/*
 * author Arya Permana - Kirin
 * created on 10-06-2025-15h-24m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Resources\Web;

use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Actions\Helpers\Images\GetPictureSources;
use App\Models\Helpers\Media;

/**
 * @property mixed $slug
 * @property mixed $code
 * @property mixed $name
 * @property mixed $canonical_url
 * @property mixed $web_images
 * @property mixed $title
 */
class WebBlockCollectionResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        $imageSources = null;
        $webImages = json_decode(trim($this->web_images, '"'), true) ?? [];
        $media        = Media::find($this->image_id);
        if ($media) {
            $width  = 720;
            $height = 720;


            $image        = $media->getImage()->resize($width, $height);
            $imageSources = GetPictureSources::run($image);
        }


        return [
            'code'       => $this->code,
            'name'       => $this->name,
            'title'      => $this->title ?? $this->name ?? $this->code,
            'url'        => $this->canonical_url,
            'web_images' => $webImages,
            'image'      =>  $imageSources
        ];
    }
}
