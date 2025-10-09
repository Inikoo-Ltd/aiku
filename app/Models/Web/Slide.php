<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 25 Jul 2023 12:56:21 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\Web;

use App\Models\Helpers\Media;
use App\Models\Helpers\Snapshot;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property string|null $ulid
 * @property int $snapshot_id
 * @property bool $visibility
 * @property array<array-key, mixed>|null $layout
 * @property int|null $image_id
 * @property int|null $mobile_image_id
 * @property int|null $tablet_image_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $deleted_at
 * @property string|null $delete_comment
 * @property-read Media|null $image
 * @property-read Media|null $imageMobile
 * @property-read Media|null $imageTablet
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, Media> $media
 * @property-read Snapshot|null $snapshot
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Slide newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Slide newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Slide query()
 * @mixin \Eloquent
 */
class Slide extends Model implements HasMedia
{
    use InteractsWithMedia;


    protected $casts = [
        'layout'   => 'array',
    ];

    protected $attributes = [
        'layout'   => '{}',
    ];

    protected $guarded = [];

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(Snapshot::class);
    }

    public function image(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'image_id');
    }

    public function imageMobile(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'mobile_image_id');
    }

    public function imageTablet(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'tablet_image_id');
    }

}
