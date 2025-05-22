<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Thu, 20 Oct 2022 18:49:22 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Models\Catalogue;

use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\CRM\BackInStockReminder;
use App\Models\CRM\Favourite;
use App\Models\Helpers\UniversalSearch;
use App\Models\Masters\MasterProductCategory;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasImage;
use App\Models\Traits\HasUniversalSearch;
use App\Models\Traits\InShop;
use App\Models\Web\Webpage;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as LaravelCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 *
 *
 * @property int $id
 * @property ProductCategoryTypeEnum $type
 * @property ProductCategoryStateEnum $state
 * @property int $group_id
 * @property int $organisation_id
 * @property int|null $shop_id
 * @property int|null $master_product_category_id
 * @property int|null $department_id
 * @property int|null $sub_department_id
 * @property int|null $parent_id
 * @property string $slug
 * @property string $code
 * @property string|null $name
 * @property string|null $description
 * @property int|null $image_id
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $activated_at
 * @property \Illuminate\Support\Carbon|null $discontinuing_at
 * @property \Illuminate\Support\Carbon|null $discontinued_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $fetched_at
 * @property \Illuminate\Support\Carbon|null $last_fetched_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $delete_comment
 * @property string|null $source_department_id
 * @property string|null $source_family_id
 * @property bool $follow_master
 * @property bool $show_in_website
 * @property-read LaravelCollection<int, \App\Models\Helpers\Audit> $audits
 * @property-read LaravelCollection<int, ProductCategory> $children
 * @property-read LaravelCollection<int, \App\Models\Catalogue\Collection> $collections
 * @property-read ProductCategory|null $department
 * @property-read LaravelCollection<int, BackInStockReminder> $departmentBackInStockReminders
 * @property-read LaravelCollection<int, Favourite> $departmentFavourites
 * @property-read LaravelCollection<int, BackInStockReminder> $familyBackInStockReminders
 * @property-read LaravelCollection<int, Favourite> $familyFavourites
 * @property-read Group $group
 * @property-read \App\Models\Helpers\Media|null $image
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $images
 * @property-read MasterProductCategory|null $masterProductCategory
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $media
 * @property-read \App\Models\Catalogue\ProductCategoryOrderingIntervals|null $orderingIntervals
 * @property-read \App\Models\Catalogue\ProductCategoryOrderingStats|null $orderingStats
 * @property-read Organisation $organisation
 * @property-read ProductCategory|null $parent
 * @property-read \App\Models\Catalogue\ProductCategorySalesIntervals|null $salesIntervals
 * @property-read \App\Models\Catalogue\Shop|null $shop
 * @property-read \App\Models\Catalogue\ProductCategoryStats|null $stats
 * @property-read ProductCategory|null $subDepartment
 * @property-read LaravelCollection<int, BackInStockReminder> $subDepartmentBackInStockReminders
 * @property-read LaravelCollection<int, Favourite> $subDepartmentFavourites
 * @property-read LaravelCollection<int, ProductCategory> $subDepartments
 * @property-read LaravelCollection<int, \App\Models\Catalogue\ProductCategoryTimeSeries> $timeSeries
 * @property-read UniversalSearch|null $universalSearch
 * @property-read Webpage|null $webpage
 * @method static \Database\Factories\Catalogue\ProductCategoryFactory factory($count = null, $state = [])
 * @method static Builder<static>|ProductCategory newModelQuery()
 * @method static Builder<static>|ProductCategory newQuery()
 * @method static Builder<static>|ProductCategory onlyTrashed()
 * @method static Builder<static>|ProductCategory query()
 * @method static Builder<static>|ProductCategory withTrashed()
 * @method static Builder<static>|ProductCategory withoutTrashed()
 * @mixin Eloquent
 */
class ProductCategory extends Model implements Auditable, HasMedia
{
    use HasSlug;
    use SoftDeletes;
    use HasUniversalSearch;
    use HasFactory;
    use HasHistory;
    use InShop;
    use HasImage;

    protected $guarded = [];

    protected $casts = [
        'data'             => 'array',
        'state'            => ProductCategoryStateEnum::class,
        'type'             => ProductCategoryTypeEnum::class,
        'activated_at'     => 'datetime',
        'discontinuing_at' => 'datetime',
        'discontinued_at'  => 'datetime',
        'fetched_at'       => 'datetime',
        'last_fetched_at'  => 'datetime',
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    public function generateTags(): array
    {
        return [
            'catalogue',
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
            ->generateSlugsFrom(function () {
                return $this->code.'-'.$this->shop->code;
            })
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->slugsShouldBeNoLongerThan(128);
    }

    public function stats(): HasOne
    {
        return $this->hasOne(ProductCategoryStats::class);
    }

    public function orderingIntervals(): HasOne
    {
        return $this->hasOne(ProductCategoryOrderingIntervals::class);
    }

    public function salesIntervals(): HasOne
    {
        return $this->hasOne(ProductCategorySalesIntervals::class);
    }

    public function orderingStats(): HasOne
    {
        return $this->hasOne(ProductCategoryOrderingStats::class);
    }

    public function timeSeries(): HasMany
    {
        return $this->hasMany(ProductCategoryTimeSeries::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'department_id');
    }

    public function subDepartments(): HasMany
    {
        return $this->hasMany(ProductCategory::class, 'sub_department_id');
    }


    public function parent(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(ProductCategory::class, 'parent_id');
    }

    public function getFamilies(): LaravelCollection
    {
        return $this->children()->where('type', ProductCategoryTypeEnum::FAMILY)->get();
    }

    public function getSubDepartmentFamilies(): LaravelCollection
    {
        return $this->subDepartments()->where('type', ProductCategoryTypeEnum::FAMILY)->get();
    }

    public function getProducts(): LaravelCollection
    {
        return match ($this->type) {
            ProductCategoryTypeEnum::DEPARTMENT => Product::where('department_id', $this->id)->get(),
            ProductCategoryTypeEnum::FAMILY     => Product::where('family_id', $this->id)->get(),
            ProductCategoryTypeEnum::SUB_DEPARTMENT => Product::where('sub_department_id', $this->id)->get(),
        };
    }
    public function collections(): MorphToMany
    {
        return $this->morphToMany(Collection::class, 'model', 'model_has_collections')->withTimestamps();
    }

    public function webpage(): MorphOne
    {
        return $this->morphOne(Webpage::class, 'model');
    }

    public function departmentFavourites(): HasMany
    {
        return $this->hasMany(Favourite::class, 'department_id');
    }

    public function familyFavourites(): HasMany
    {
        return $this->hasMany(Favourite::class, 'family_id');
    }

    public function subDepartmentFavourites(): HasMany
    {
        return $this->hasMany(Favourite::class, 'sub_department_id');
    }

    public function departmentBackInStockReminders(): HasMany
    {
        return $this->hasMany(BackInStockReminder::class, 'department_id');
    }

    public function familyBackInStockReminders(): HasMany
    {
        return $this->hasMany(BackInStockReminder::class, 'family_id');
    }

    public function subDepartmentBackInStockReminders(): HasMany
    {
        return $this->hasMany(BackInStockReminder::class, 'sub_department_id');
    }

    public function masterProductCategory(): BelongsTo
    {
        return $this->belongsTo(MasterProductCategory::class);
    }

}
