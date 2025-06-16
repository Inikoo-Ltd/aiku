<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 30 May 2024 08:37:52 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Traits;

use App\Actions\Helpers\Images\GetPictureSources;
use App\Models\Helpers\Media;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Spatie\MediaLibrary\InteractsWithMedia;

trait HasImage
{
    use InteractsWithMedia;

    public function images(): MorphToMany
    {
        return $this->morphToMany(Media::class, 'model', 'model_has_media');
    }
    public function image(): HasOne
    {
        return $this->hasOne(Media::class, 'id', 'image_id');
    }

    public function imageSources($width = 0, $height = 0, $getImage = 'image')
    {
        if ($this->{$getImage}) {
            $avatarThumbnail = $this->{$getImage}->getImage()->resize($width, $height);
            return GetPictureSources::run($avatarThumbnail);
        }
        return null;
    }

    public function seoImage(): HasOne
    {
        return $this->hasOne(Media::class, 'id', 'seo_image_id');
    }

}
