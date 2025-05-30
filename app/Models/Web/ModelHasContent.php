<?php
/*
 * author Arya Permana - Kirin
 * created on 30-05-2025-13h-06m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Models\Web;

use App\Enums\Web\ModelHasContent\ModelHasContentTypeEnum;
use App\Models\Traits\HasImage;
use App\Models\Traits\HasUniversalSearch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\MediaLibrary\HasMedia;

class ModelHasContent extends Model implements HasMedia
{
    use HasUniversalSearch;
    use HasImage;

    protected $casts = [
        'type'            => ModelHasContentTypeEnum::class,
    ];

    protected $guarded = [];

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    public function model(): MorphTo
    {
        return $this->morphTo();
    }
}