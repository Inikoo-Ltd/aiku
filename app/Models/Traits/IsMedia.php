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
