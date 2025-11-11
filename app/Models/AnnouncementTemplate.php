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
 * @property string $code
 * @property int|null $screenshot_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $category
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, Media> $media
 * @property-read int|null $media_count
 * @property-read Media|null $screenshot
 * @method static \Illuminate\Database\Eloquent\Builder|AnnouncementTemplate newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|AnnouncementTemplate newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|AnnouncementTemplate query()
 * @method static \Illuminate\Database\Eloquent\Builder|AnnouncementTemplate whereCategory($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AnnouncementTemplate whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AnnouncementTemplate whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AnnouncementTemplate whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AnnouncementTemplate whereScreenshotId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AnnouncementTemplate whereUpdatedAt($value)
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
