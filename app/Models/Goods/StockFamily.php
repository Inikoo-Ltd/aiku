<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Dec 2024 12:06:14 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Goods;

use App\Enums\Goods\StockFamily\StockFamilyStateEnum;
use App\Models\Helpers\UniversalSearch;
use App\Models\Inventory\OrgStockFamily;
use App\Models\SysAdmin\Group;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasImage;
use App\Models\Traits\HasUniversalSearch;
use App\Models\Traits\InGroup;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * App\Models\SupplyChain\StockFamily
 *
 * @property int $id
 * @property int $group_id
 * @property string $slug
 * @property StockFamilyStateEnum $state
 * @property string $code
 * @property string|null $name
 * @property string|null $description
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $activated_at
 * @property string|null $discontinuing_at
 * @property string|null $discontinued_at
 * @property \Illuminate\Support\Carbon|null $fetched_at
 * @property \Illuminate\Support\Carbon|null $last_fetched_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $source_slug
 * @property string|null $source_id
 * @property-read Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read Group $group
 * @property-read \App\Models\Helpers\Media|null $image
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $images
 * @property-read \App\Models\Goods\StockFamilyIntervals|null $intervals
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $media
 * @property-read Collection<int, OrgStockFamily> $orgStockFamilies
 * @property-read \App\Models\Goods\StockFamilySalesIntervals|null $salesIntervals
 * @property-read \App\Models\Helpers\Media|null $seoImage
 * @property-read \App\Models\Goods\StockFamilyStats|null $stats
 * @property-read Collection<int, \App\Models\Goods\Stock> $stocks
 * @property-read Collection<int, \App\Models\Goods\StockFamilyTimeSeries> $timeSeries
 * @property-read UniversalSearch|null $universalSearch
 * @method static \Database\Factories\Goods\StockFamilyFactory factory($count = null, $state = [])
 * @method static Builder<static>|StockFamily newModelQuery()
 * @method static Builder<static>|StockFamily newQuery()
 * @method static Builder<static>|StockFamily onlyTrashed()
 * @method static Builder<static>|StockFamily query()
 * @method static Builder<static>|StockFamily withTrashed()
 * @method static Builder<static>|StockFamily withoutTrashed()
 * @mixin Eloquent
 */
class StockFamily extends Model implements HasMedia, Auditable
{
    use HasSlug;
    use SoftDeletes;
    use HasImage;
    use InGroup;
    use HasHistory;
    use HasUniversalSearch;
    use HasFactory;

    protected $casts = [
        'data'                        => 'array',
        'state'                       => StockFamilyStateEnum::class,
        'fetched_at'                  => 'datetime',
        'last_fetched_at'             => 'datetime',
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    protected $guarded = [];

    public function generateTags(): array
    {
        return [
            'goods'
        ];
    }

    protected array $auditInclude = [
        'code',
        'name',
        'description'
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('code')
            ->slugsShouldBeNoLongerThan(128)
            ->doNotGenerateSlugsOnUpdate()
            ->saveSlugsTo('slug');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    public function stats(): HasOne
    {
        return $this->hasOne(StockFamilyStats::class);
    }

    public function intervals(): HasOne
    {
        return $this->hasOne(StockFamilyIntervals::class);
    }

    public function salesIntervals(): HasOne
    {
        return $this->hasOne(StockFamilySalesIntervals::class);
    }

    public function orgStockFamilies(): HasMany
    {
        return $this->hasMany(OrgStockFamily::class);
    }

    public function timeSeries(): HasMany
    {
        return $this->hasMany(StockFamilyTimeSeries::class);
    }

}
