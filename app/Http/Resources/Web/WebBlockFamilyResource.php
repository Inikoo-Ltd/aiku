<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Jun 2025 14:27:36 Central Indonesia Time, Shanghai, China
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Web;

use App\Actions\Helpers\Images\GetImgProxyUrl;
use App\Actions\Web\WebBlock\Concerns\WithIrisImageVariants;
use App\Http\Resources\HasSelfCall;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\HasCardWebImages;
use Illuminate\Support\Arr;

class WebBlockFamilyResource extends JsonResource
{
    use HasCardWebImages;
    use HasSelfCall;
    use WithIrisImageVariants;

    public function toArray($request): array
    {
        /** @var ProductCategory $family */
        $family = $this->resource;

        return [
            'slug'                      => $family->slug,
            'code'                      => $family->code,
            'name'                      => $family->name,
            'description'               => $family->description,
            'description_title'         => $family->description_title,
            'description_extra'         => $family->description_extra,
            'id'                        => $family->id,
            'description_image'         => collect(Arr::get($family->web_images, 'description', []))->map(fn ($slot) => $this->getResizedSlot($slot))->filter()->all(),
            'description_video'         => $family->desc_video_url,
            'extra_description_image'   => collect(Arr::get($family->web_images, 'extraDescription', []))->map(fn ($slot) => $this->getResizedSlot($slot))->filter()->all(),
            'url'                       => $family->webpage->url,
            'offers_data'               => $family->offers_data,
            'tags'                      => $family->tradeUnitFamily?->tags()->limit(3)->get()->map(fn ($tag) => ['name' => $tag->name, 'web_image' => $this->getPictureFormats($tag->web_image)])->all(),
            'faq'                       => $family->faq,
            'marketing_material_route'  => [
                'name'          => 'iris.catalogue.feeds.product_category.download_img',
                'parameters'    => [
                    'productCategory'   => $family->slug,
                    'type'              => 'products_images'
                ]
            ],
        ];
    }

    /**
     * Description image slots store whatever urls existed when the family was saved,
     * including unresized originals; rebuild them capped at 1200px.
     *
     * @return array<string, string>|null
     */
    private function getResizedSlot(mixed $slot): ?array
    {
        if (!is_array($slot)) {
            return null;
        }

        $originalUrl = Arr::get($slot, 'original');
        if (is_array($originalUrl)) {
            $originalUrl = Arr::get($originalUrl, 'original');
        }

        $media = is_string($originalUrl) ? $this->findMediaFromImgProxyUrl($originalUrl) : null;
        if (!$media) {
            return $this->getPictureFormats($slot);
        }

        $resized = [
            'original' => GetImgProxyUrl::run($media->getImage()->resize(1200, 1200)),
        ];
        if (in_array('avif', config('img-proxy.formats')) && !$media->is_animated) {
            $resized['avif'] = GetImgProxyUrl::run($media->getImage()->resize(1200, 1200)->extension('avif'));
        }
        if (in_array('webp', config('img-proxy.formats'))) {
            $resized['webp'] = GetImgProxyUrl::run($media->getImage()->resize(1200, 1200)->extension('webp'));
        }
        if (Arr::has($slot, 'alt')) {
            $resized['alt'] = Arr::get($slot, 'alt');
        }

        return $resized;
    }
}
