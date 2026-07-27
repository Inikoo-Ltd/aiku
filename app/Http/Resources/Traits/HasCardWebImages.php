<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 13 Jul 2026 16:20:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Traits;

use Illuminate\Support\Arr;

trait HasCardWebImages
{
    /**
     * Product cards only render web_images.{main,secondary}.gallery through a picture
     * element reading avif/webp/original (+ retina variants). When secondary is
     * missing, it falls back to the first non-main gallery in web_images.all.
     *
     * @return array<string, array<string, array<string, string>>>
     */
    protected function getCardWebImages(mixed $webImages): array
    {
        $cardWebImages = [];

        foreach (['main', 'secondary'] as $slot) {
            $gallery = Arr::get($webImages, "$slot.gallery");
            if (!is_array($gallery) && $slot === 'secondary') {
                $gallery = $this->getSecondaryGalleryFallback($webImages);
            }
            if (!is_array($gallery)) {
                continue;
            }

            $cardWebImages[$slot] = [
                'gallery' => Arr::only($gallery, ['avif', 'avif_2x', 'webp', 'webp_2x', 'original', 'original_2x']),
            ];
        }

        return $cardWebImages;
    }

    /**
     * First gallery in web_images.all that is not the main image.
     *
     * @return array<string, string>|null
     */
    protected function getSecondaryGalleryFallback(mixed $webImages): ?array
    {
        $mainOriginal = Arr::get($webImages, 'main.gallery.original');

        foreach (Arr::get($webImages, 'all', []) as $image) {
            $gallery = Arr::get($image, 'gallery');
            if (is_array($gallery) && Arr::get($gallery, 'original') !== $mainOriginal) {
                return $gallery;
            }
        }

        return null;
    }

    /**
     * Keep only the formats the Image component's picture element reads.
     *
     * @return array<string, string>|null
     */
    protected function getPictureFormats(mixed $image): ?array
    {
        if (!is_array($image)) {
            return null;
        }

        return Arr::only($image, ['avif', 'avif_2x', 'webp', 'webp_2x', 'original', 'original_2x', 'alt']);
    }
}
