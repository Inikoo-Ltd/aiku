<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Dec 2024 02:15:29 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Masters;

use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\SysAdmin\Group;
use App\Models\Traits\HasHistory;
use Illuminate\Database\Eloquent\Collection as LaravelCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 *
 *
 * @property int $id
 * @property int $group_id
 * @property string $slug
 * @property string $code
 * @property string|null $name
 * @property bool $status
 * @property ShopTypeEnum $type
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read LaravelCollection<int, \App\Models\Helpers\Audit> $audits
 * @property-read Group $group
 * @property-read LaravelCollection<int, \App\Models\Masters\MasterAsset> $masterAssets
 * @property-read LaravelCollection<int, \App\Models\Masters\MasterProductCategory> $masterProductCategories
 * @property-read \App\Models\Masters\MasterShopOrderingIntervals|null $orderingIntervals
 * @property-read \App\Models\Masters\MasterShopOrderingStats|null $orderingStats
 * @property-read \App\Models\Masters\MasterShopSalesIntervals|null $salesIntervals
 * @property-read \App\Models\Masters\MasterShopStats|null $stats
 * @property-read LaravelCollection<int, \App\Models\Masters\MasterShopTimeSeries> $timeSeries
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterShop newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterShop newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterShop onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterShop query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterShop withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterShop withoutTrashed()
 * @mixin \Eloquent
 */
class MasterShop extends Model implements Auditable
{
    use SoftDeletes;
    use HasSlug;
    use HasHistory;

    protected $casts = [
        'data'            => 'array',
        'type'            => ShopTypeEnum::class,
        'status'           => 'boolean',
    ];

    protected $attributes = [
        'data'     => '{}',
    ];

    protected $guarded = [];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function generateTags(): array
    {
        return [
            'goods'
        ];
    }

    protected array $auditInclude = [
        'code',
        'name',
    ];


    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('code')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->slugsShouldBeNoLongerThan(64);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function stats(): HasOne
    {
        return $this->hasOne(MasterShopStats::class);
    }

    public function orderingStats(): HasOne
    {
        return $this->hasOne(MasterShopOrderingStats::class);
    }

    public function orderingIntervals(): HasOne
    {
        return $this->hasOne(MasterShopOrderingIntervals::class);
    }

    public function salesIntervals(): HasOne
    {
        return $this->hasOne(MasterShopSalesIntervals::class);
    }

    public function masterProductCategories(): HasMany
    {
        return $this->hasMany(MasterProductCategory::class);
    }

    public function getMasterDepartments(): LaravelCollection
    {
        return $this->masterProductCategories()->where('type', ProductCategoryTypeEnum::DEPARTMENT)->get();
    }

    public function getMasterSubDepartments(): LaravelCollection
    {
        return $this->masterProductCategories()->where('type', ProductCategoryTypeEnum::SUB_DEPARTMENT)->get();
    }

    public function getMasterFamilies(): LaravelCollection
    {
        return $this->masterProductCategories()->where('type', ProductCategoryTypeEnum::FAMILY)->get();
    }

    public function getMasterProducts(): BelongsToMany
    {
        return $this->belongsToMany(MasterAsset::class, 'master_shop_has_master_products')
            ->withTimestamps();
    }


    public function timeSeries(): HasMany
    {
        return $this->hasMany(MasterShopTimeSeries::class);
    }

    public function masterAssets(): HasMany
    {
        return $this->hasMany(MasterAsset::class);
    }

}
