<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 31 May 2024 14:30:10 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Traits;

use App\Models\Helpers\Media;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Spatie\MediaLibrary\InteractsWithMedia;

trait HasAttachments
{
    use InteractsWithMedia;

    public function attachments(): MorphToMany
    {
        return $this->morphToMany(Media::class, 'model', 'model_has_attachments')
            ->withPivot('scope', 'caption', 'fetched_at', 'last_fetched_at', 'source_id', 'sources')
            ->withTimestamps();
    }
}
