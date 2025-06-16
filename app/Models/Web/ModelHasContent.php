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

/**
 *
 *
 * @property int $id
 * @property ModelHasContentTypeEnum $type
 * @property string $model_type
 * @property int $model_id
 * @property string $title
 * @property string $text
 * @property int|null $image_id
 * @property int $position
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Helpers\Media|null $image
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $images
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $media
 * @property-read Model|\Eloquent $model
 * @property-read \App\Models\Helpers\Media|null $seoImage
 * @property-read \App\Models\Helpers\UniversalSearch|null $universalSearch
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModelHasContent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModelHasContent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModelHasContent query()
 * @mixin \Eloquent
 */
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
