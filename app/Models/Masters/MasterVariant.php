<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 10 Dec 2025 21:29:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Models\Masters;

use App\Models\Traits\HasHistory;
use App\Models\Traits\HasImage;
use App\Models\Traits\HasUniversalSearch;
use App\Models\Traits\InMasterShop;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * @property int $id
 * @property int $group_id
 * @property int|null $master_shop_id
 * @property int|null $master_family_id
 * @property int|null $master_sub_department_id
 * @property int|null $master_department_id
 * @property string $code
 * @property int|null $leader_id
 * @property int $number_minions
 * @property int $number_dimensions
 * @property int $number_used_slots
 * @property int $number_used_slots_for_sale
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string $slug
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\Helpers\Media|null $image
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $images
 * @property-read \App\Models\Masters\MasterShop|null $masterShop
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $media
 * @property-read \App\Models\Masters\MasterVariantOrderingIntervals|null $orderingIntervals
 * @property-read \App\Models\Masters\MasterVariantOrderingStats|null $orderingStats
 * @property-read \App\Models\Masters\MasterVariantSalesIntervals|null $salesIntervals
 * @property-read \App\Models\Helpers\Media|null $seoImage
 * @property-read \App\Models\Masters\MasterVariantStats|null $stats
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Masters\MasterVariantTimeSeries> $timeSeries
 * @property-read \App\Models\Helpers\UniversalSearch|null $universalSearch
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterVariant newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterVariant newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterVariant onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterVariant query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterVariant withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterVariant withoutTrashed()
 * @mixin \Eloquent
 */
class MasterVariant extends Model implements Auditable, HasMedia
{
    use HasSlug;
    use SoftDeletes;
    use HasUniversalSearch;
    use HasHistory;
    use HasImage;
    use InMasterShop;

    protected $guarded = [];
    protected $casts = [
        'data'      => 'array',
    ];

    public function generateTags(): array
    {
        return [
            'goods',
        ];
    }

    protected array $auditInclude = [
        'code',
        'name',
        'description',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('code')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->slugsShouldBeNoLongerThan(128);
    }

    public function masterFamily(): BelongsTo
    {
        return $this->belongsTo(MasterProductCategory::class, 'master_family_id');
    }

    public function stats(): HasOne
    {
        return $this->hasOne(MasterVariantStats::class);
    }

    public function salesIntervals(): HasOne
    {
        return $this->hasOne(MasterVariantSalesIntervals::class);
    }

    public function orderingStats(): HasOne
    {
        return $this->hasOne(MasterVariantOrderingStats::class);
    }

    public function orderingIntervals(): HasOne
    {
        return $this->hasOne(MasterVariantOrderingIntervals::class);
    }

    public function timeSeries(): HasMany
    {
        return $this->hasMany(MasterVariantTimeSeries::class);
    }


}
