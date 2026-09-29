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
use Illuminate\Database\Eloquent\Collection;
use Spatie\MediaLibrary\InteractsWithMedia;

trait HasImage
{
    use InteractsWithMedia;

    public function images(): MorphToMany
    {
        return $this->morphToMany(Media::class, 'model', 'model_has_media')->withTimestamps()->withPivot('scope', 'caption', 'sub_scope', 'is_public', 'is_caption_reviewed', 'source_caption');
    }

    /**
     * The order a marketplace or a download should show the pictures in: the main image first,
     * because the first one sent becomes the listing's main picture, then as arranged on the
     * product. The relation alone has no order, so the database returns them however it likes.
     *
     * @return Collection<int, Media>
     */
    public function orderedImages(): Collection
    {
        return $this->images()
            ->orderByRaw('model_has_media.media_id = ? desc', [$this->image_id ?? 0])
            ->orderByPivot('position')
            ->orderBy('media.id')
            ->get();
    }

    public function image(): HasOne
    {
        return $this->hasOne(Media::class, 'id', 'image_id');
    }

    public function imageSources($width = 800, $height = 800, $getImage = 'image')
    {
        if ($this->{$getImage}) {
            $avatarThumbnail = $this->{$getImage}->getImage()->resize($width, $height);
            return GetPictureSources::run($avatarThumbnail);
        }
        return null;
    }

    public function audio(): HasOne
    {
        return $this->hasOne(Media::class, 'id', 'audio_id');
    }

    public function seoImage(): HasOne
    {
        return $this->hasOne(Media::class, 'id', 'seo_image_id');
    }

}
