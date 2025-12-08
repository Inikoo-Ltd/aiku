<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 17 Feb 2024 01:36:01 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Traits;

use App\Models\Helpers\Language;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Sluggable\HasSlug;

trait IsUserable
{
    use HasApiTokens;
    use HasFactory;
    use HasHistory;
    use HasSlug;
    use HasUniversalSearch;
    use SoftDeletes;

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }
}
