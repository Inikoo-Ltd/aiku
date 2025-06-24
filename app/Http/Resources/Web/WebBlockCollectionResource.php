<?php

/*
 * author Arya Permana - Kirin
 * created on 10-06-2025-15h-24m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Resources\Web;

use App\Actions\Helpers\Images\GetPictureSources;
use App\Http\Resources\HasSelfCall;
use App\Models\Helpers\Media;
use Illuminate\Http\Resources\Json\JsonResource;


class WebBlockCollectionResource extends JsonResource
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
            'url'   => $this->url,
            'web_images' =>  $this->web_images,
            'products_route' => [
                'name' => 'grp.json.collection.products.index',
                'parameters' => [
                    'collection' => $this->slug,
                ]
            ],
            'families_route' => [
                'name' => 'grp.json.collection.families.index',
                'parameters' => [
                    'collection' => $this->slug,
                ]
            ]
        ];
    }
}
