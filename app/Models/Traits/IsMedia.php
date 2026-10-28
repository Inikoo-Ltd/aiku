<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 10 Aug 2023 12:14:27 Malaysia Time, Pantai Lembeng, Bali
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\Traits;

use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

trait IsMedia
{
    use HasSlug;

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(function () {
                return preg_replace('/\.(png|jpg|jpeg|webp|avif|svg)$/i', '', $this->name);
            })
            ->doNotGenerateSlugsOnUpdate()
            ->saveSlugsTo('slug')
            ->slugsShouldBeNoLongerThan(128);
    }


    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Pasted pictures are nearly all called image.png, img.jpg or image001.png, and the package
     * looked for a free number one query at a time: some 2,200 lookups for each new "image",
     * more with every upload. Doubling until a number is free and then halving back finds the
     * same next number (the one after the last taken, when the taken ones run from 1 without
     * gaps) in a few dozen lookups, and only ever returns a slug it saw free.
     */
    protected function makeSlugUnique(string $slug): string
    {
        if ($slug !== '' && !$this->otherRecordExistsWithSlug($slug)) {
            return $slug;
        }

        $numbered = fn (int $number): string => $slug.$this->slugOptions->slugSeparator.$number;

        $free = 1;
        while ($this->otherRecordExistsWithSlug($numbered($free))) {
            $free *= 2;
        }

        $taken = intdiv($free, 2);
        while ($free - $taken > 1) {
            $middle = intdiv($taken + $free, 2);
            if ($this->otherRecordExistsWithSlug($numbered($middle))) {
                $taken = $middle;
            } else {
                $free = $middle;
            }
        }

        return $numbered($free);
    }


    public function getImgProxyFilename(): string
    {
        if (config('media-library.disk_name') == 'media-r2') {
            return 's3://'.config('filesystems.disks.media-r2.bucket').'/'.$this->getPath();
        }

        return $this->getLocalImgProxyFilename();
    }

    public function getLocalImgProxyFilename(): string
    {
        return 'local://media/'.$this->getPathRelativeToRoot();
    }


}
