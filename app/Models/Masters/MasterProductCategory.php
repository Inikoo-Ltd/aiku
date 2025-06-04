<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Dec 2024 02:15:29 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Masters;

use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\SysAdmin\Group;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasImage;
use App\Models\Traits\HasUniversalSearch;
use App\Models\Traits\InGroup;
use Illuminate\Database\Eloquent\Collection as LaravelCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 *
 *
 * @property int $id
 * @property int $group_id
 * @property int $master_shop_id
 * @property MasterProductCategoryTypeEnum $type
 * @property bool $status
 * @property int|null $master_department_id
 * @property int|null $master_sub_department_id
 * @property int|null $master_parent_id
 * @property string $slug
 * @property string $code
 * @property string|null $name
 * @property string|null $description
 * @property int|null $image_id
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $fetched_at
 * @property \Illuminate\Support\Carbon|null $last_fetched_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $source_department_id
 * @property string|null $source_family_id
 * @property bool $show_in_website
 * @property-read LaravelCollection<int, \App\Models\Helpers\Audit> $audits
 * @property-read LaravelCollection<int, MasterProductCategory> $children
 * @property-read Group $group
 * @property-read \App\Models\Helpers\Media|null $image
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $images
 * @property-read MasterProductCategory|null $masterDepartment
 * @property-read LaravelCollection<int, MasterProductCategory> $masterProductCategories
 * @property-read \App\Models\Masters\MasterShop $masterShop
 * @property-read MasterProductCategory|null $masterSubDepartment
 * @property-read LaravelCollection<int, MasterProductCategory> $masterSubDepartments
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $media
 * @property-read \App\Models\Masters\MasterProductCategoryOrderingIntervals|null $orderingIntervals
 * @property-read \App\Models\Masters\MasterProductCategoryOrderingStats|null $orderingStats
 * @property-read MasterProductCategory|null $parent
 * @property-read \App\Models\Masters\MasterProductCategorySalesIntervals|null $salesIntervals
 * @property-read \App\Models\Helpers\Media|null $seoImage
 * @property-read \App\Models\Masters\MasterProductCategoryStats|null $stats
 * @property-read LaravelCollection<int, \App\Models\Masters\MasterProductCategoryTimeSeries> $timeSeries
 * @property-read \App\Models\Helpers\UniversalSearch|null $universalSearch
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterProductCategory newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterProductCategory newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterProductCategory onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterProductCategory query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterProductCategory withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterProductCategory withoutTrashed()
 * @mixin \Eloquent
 */
class MasterProductCategory extends Model implements Auditable, HasMedia
{
    use HasSlug;
    use SoftDeletes;
    use HasUniversalSearch;
    use HasHistory;
    use HasImage;
    use InGroup;

    protected $guarded = [];

    protected $casts = [
        'data'            => 'array',
        'type'            => MasterProductCategoryTypeEnum::class,
        'fetched_at'      => 'datetime',
        'last_fetched_at' => 'datetime',
    ];

    protected $attributes = [
        'data' => '{}',
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


    public function stats(): HasOne
    {
        return $this->hasOne(MasterProductCategoryStats::class);
    }

    public function orderingIntervals(): HasOne
    {
        return $this->hasOne(MasterProductCategoryOrderingIntervals::class);
    }

    public function salesIntervals(): HasOne
    {
        return $this->hasOne(MasterProductCategorySalesIntervals::class);
    }

    public function orderingStats(): HasOne
    {
        return $this->hasOne(MasterProductCategoryOrderingStats::class);
    }

    public function timeSeries(): HasMany
    {
        return $this->hasMany(MasterProductCategoryTimeSeries::class);
    }


    public function masterProductCategories(): HasMany
    {
        return $this->hasMany(MasterProductCategory::class);
    }

    public function masterDepartment(): BelongsTo
    {
        return $this->belongsTo(MasterProductCategory::class, 'master_department_id');
    }

    public function masterSubDepartment(): BelongsTo
    {
        return $this->belongsTo(MasterProductCategory::class, 'master_sub_department_id');
    }

    public function masterSubDepartments(): HasMany
    {
        return $this->hasMany(MasterProductCategory::class, 'master_department_id');
    }


    public function parent(): BelongsTo
    {
        return $this->belongsTo(MasterProductCategory::class, 'master_parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(MasterProductCategory::class, 'master_parent_id');
    }

    public function masterFamilies(): LaravelCollection
    {
        return $this->children()->where('type', ProductCategoryTypeEnum::FAMILY)->get();
    }


    public function masterAssets(): HasMany|null
    {
        return match ($this->type) {
            MasterProductCategoryTypeEnum::DEPARTMENT => $this->hasMany(MasterAsset::class, 'master_department_id'),
            MasterProductCategoryTypeEnum::FAMILY => $this->hasMany(MasterAsset::class, 'master_family_id'),
            MasterProductCategoryTypeEnum::SUB_DEPARTMENT => $this->hasMany(MasterAsset::class, 'master_sub_department_id')
        };
    }

    public function masterShop(): BelongsTo
    {
        return $this->belongsTo(MasterShop::class);
    }


}
