<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 13 Jul 2023 16:02:07 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\Web;

use App\Actions\Utils\Abbreviate;

;

use App\Enums\Web\Banner\BannerStateEnum;
use App\Models\Helpers\Deployment;
use App\Models\Helpers\Media;
use App\Models\Helpers\Snapshot;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasUniversalSearch;
use App\Models\Traits\InWebsite;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $shop_id
 * @property int $website_id
 * @property int|null $web_block_id
 * @property string $ulid
 * @property string $type
 * @property string $slug
 * @property string $name
 * @property BannerStateEnum $state
 * @property int|null $unpublished_snapshot_id
 * @property int|null $live_snapshot_id
 * @property string $date
 * @property string|null $live_at
 * @property string|null $switch_off_at
 * @property array<array-key, mixed> $compiled_layout
 * @property array<array-key, mixed> $data
 * @property int|null $image_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $delete_comment
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Deployment> $deployments
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read Media|null $image
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, Media> $images
 * @property-read Snapshot|null $liveSnapshot
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, Media> $media
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \App\Models\Catalogue\Shop $shop
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Snapshot> $snapshots
 * @property-read \App\Models\Web\BannerStats|null $stats
 * @property-read \App\Models\Helpers\UniversalSearch|null $universalSearch
 * @property-read Snapshot|null $unpublishedSnapshot
 * @property-read \App\Models\Web\Website $website
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Banner newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Banner newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Banner onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Banner query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Banner withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Banner withoutTrashed()
 * @mixin \Eloquent
 */
class Banner extends Model implements HasMedia, Auditable
{
    use SoftDeletes;
    use HasSlug;
    use HasFactory;
    use InWebsite;
    use HasUniversalSearch;
    use InteractsWithMedia;
    use HasHistory;

    protected $dateFormat = 'Y-m-d H:i:s P';

    protected $casts = [
        'compiled_layout' => 'array',
        'data'            => 'array',
        'state'           => BannerStateEnum::class
    ];

    protected $attributes = [
        'compiled_layout' => '{}',
        'data'            => '{}',
    ];

    protected $guarded = [];

    public function generateTags(): array
    {
        return [
            'portfolio','banners'
        ];
    }

    protected array $auditExclude = [
        'compiled_layout','unpublished_snapshot_id'
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(function () {
                return Abbreviate::run(string:$this->name);
            })
            ->doNotGenerateSlugsOnUpdate()
            ->saveSlugsTo('slug')
            ->slugsShouldBeNoLongerThan(128);
    }

    public function snapshots(): MorphMany
    {
        return $this->morphMany(Snapshot::class, 'parent');
    }

    public function unpublishedSnapshot(): BelongsTo
    {
        return $this->belongsTo(Snapshot::class, 'unpublished_snapshot_id');
    }

    public function liveSnapshot(): BelongsTo
    {
        return $this->belongsTo(Snapshot::class, 'live_snapshot_id');
    }

    public function stats(): HasOne
    {
        return $this->hasOne(BannerStats::class);
    }

    public function image(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'image_id');
    }

    public function images(): MorphToMany
    {
        return $this->morphToMany(Media::class, 'model', 'model_has_media');
    }

    public function deployments(): MorphMany
    {
        return $this->morphMany(Deployment::class, 'model');
    }

}
