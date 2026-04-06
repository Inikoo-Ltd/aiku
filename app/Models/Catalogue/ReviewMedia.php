<?php

namespace App\Models\Catalogue;

use App\Enums\Catalogue\Review\ReviewMediaTypeEnum;
use App\Models\Helpers\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Traits\HasImage;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property int $review_id
 * @property int $media_id
 * @property ReviewMediaTypeEnum $type
 * @property int $sort_order
 * @property array<array-key, mixed> $meta
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Media|null $image
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, Media> $images
 * @property-read Media $media
 * @property-read \App\Models\Catalogue\Review $review
 * @property-read Media|null $seoImage
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewMedia newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewMedia newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewMedia query()
 * @mixin \Eloquent
 */
class ReviewMedia extends Model
{
    use HasImage;
    use InteractsWithMedia;

    protected $guarded = [];

    protected $casts = [
        'type'       => ReviewMediaTypeEnum::class,
        'sort_order' => 'integer',
        'meta'       => 'array',
    ];

    protected $attributes = [
        'meta' => '{}',
    ];

    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }


    public function isImage(): bool
    {
        return $this->type === ReviewMediaTypeEnum::IMAGE;
    }


    public function isFile(): bool
    {
        return $this->type === ReviewMediaTypeEnum::VIDEO;
    }
}
