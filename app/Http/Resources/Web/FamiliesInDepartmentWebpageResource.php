<?php

/*
 * author Louis Perez
 * created on 06-06-2026-14h-51m
 * GitHub: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Http\Resources\Web;

use App\Actions\Web\WebBlock\Concerns\WithIrisImageVariants;
use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

/**
 * @property mixed $web_images
 * @property mixed $slug
 * @property mixed $canonical_url
 * @property mixed $name
 */
class FamiliesInDepartmentWebpageResource extends JsonResource
{
    use HasSelfCall;
    use WithIrisImageVariants;

    public const array SRCSET_WIDTHS = [360, 720, 1440];

    public function toArray($request): array
    {
        $originalUrl = Arr::get($this->web_images, 'main.original');
        if (is_array($originalUrl)) {
            $originalUrl = Arr::get($originalUrl, 'original');
        }

        $srcset = null;
        $media  = $this->findMediaFromImgProxyUrl($originalUrl);
        if ($media) {
            $srcset = $this->getWidthSrcSets($media, self::SRCSET_WIDTHS);
        }

        return [
            'slug'   => $this->slug,
            'name'   => $this->name,
            'url'    => $this->canonical_url,
            'image'  => Arr::get($this->web_images, 'main.gallery', Arr::get($this->web_images, 'main.original')),
            'srcset' => $srcset,
        ];
    }
}
