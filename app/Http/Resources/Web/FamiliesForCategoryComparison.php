<?php

/*
 * Author Louis Perez
 * Created on 26-08-2026-09h-54m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Http\Resources\Web;

use App\Actions\Web\WebBlock\Concerns\WithIrisImageVariants;
use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

/**
 * @property mixed $id
 * @property mixed $slug
 * @property mixed $code
 * @property mixed $name
 * @property mixed $category_comparison
 * @property mixed $web_images
 * @property mixed $canonical_url
 * @property mixed $is_current
 */
class FamiliesForCategoryComparison extends JsonResource
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
            'id'                  => $this->id,
            'slug'                => $this->slug,
            'code'                => $this->code,
            'name'                => $this->name,
            'url'                 => $this->canonical_url,
            'image'               => Arr::get($this->web_images, 'main.gallery', Arr::get($this->web_images, 'main.original')),
            'srcset'              => $srcset,
            'is_current'          => (bool)$this->is_current,
            'category_comparison' => $this->category_comparison,
        ];
    }
}
