<?php

namespace App\Models;

use App\Models\Helpers\Media;
use App\Models\Traits\HasImage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;

/**
 * App\Models\AnnouncementTemplate
 *
 * @property int $id
 * @property int $group_id
 * @property string $code
 * @property int|null $screenshot_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Media|null $image
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, Media> $images
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, Media> $media
 * @property-read Media|null $screenshot
 * @property-read Media|null $seoImage
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnnouncementTemplate newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnnouncementTemplate newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnnouncementTemplate query()
 * @mixin \Eloquent
 */
class AnnouncementTemplate extends Model implements HasMedia
{
    use HasFactory;
    use HasImage;

    protected $guarded = [];

    public function screenshot(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'screenshot_id');
    }
}
