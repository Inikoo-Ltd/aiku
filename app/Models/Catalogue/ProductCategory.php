<?php
/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Thu, 20 Oct 2022 18:49:22 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Models\Catalogue;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateDepartments;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Search\UniversalSearch;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasUniversalSearch;
use App\Models\Traits\InShop;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * App\Models\Catalogue\Department
 *
 * @property int $id
 * @property ProductCategoryTypeEnum $type
 * @property int $group_id
 * @property int $organisation_id
 * @property int|null $shop_id
 * @property int|null $department_id
 * @property int|null $product_category_id
 * @property string $slug
 * @property string $code
 * @property string|null $name
 * @property string|null $description
 * @property int|null $image_id
 * @property string $parent_type
 * @property int $parent_id
 * @property ProductCategoryStateEnum|null $state
 * @property array $data
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property string|null $delete_comment
 * @property string|null $source_department_id
 * @property string|null $source_family_id
 * @property-read Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read Collection<int, ProductCategory> $departments
 * @property-read Group $group
 * @property-read Organisation $organisation
 * @property-read Model|\Eloquent $parent
 * @property-read \App\Models\Catalogue\ProductCategorySalesIntervals|null $salesIntervals
 * @property-read \App\Models\Catalogue\Shop|null $shop
 * @property-read \App\Models\Catalogue\ProductCategoryStats|null $stats
 * @property-read UniversalSearch|null $universalSearch
 * @method static \Database\Factories\Catalogue\ProductCategoryFactory factory($count = null, $state = [])
 * @method static Builder|ProductCategory newModelQuery()
 * @method static Builder|ProductCategory newQuery()
 * @method static Builder|ProductCategory onlyTrashed()
 * @method static Builder|ProductCategory query()
 * @method static Builder|ProductCategory withTrashed()
 * @method static Builder|ProductCategory withoutTrashed()
 * @mixin Eloquent
 */
class ProductCategory extends Model implements Auditable
{
    use HasSlug;
    use SoftDeletes;
    use HasUniversalSearch;
    use HasFactory;
    use HasHistory;
    use InShop;

    protected $guarded = [];

    protected $casts = [
        'data'  => 'array',
        'state' => ProductCategoryStateEnum::class,
        'type'  => ProductCategoryTypeEnum::class,
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    // todo move this to the action
    protected static function booted(): void
    {
        static::updated(function (ProductCategory $department) {
            if ($department->wasChanged('state')) {
                ShopHydrateDepartments::dispatch($department->shop);
            }
        });
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('code')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->slugsShouldBeNoLongerThan(64);
    }

    public function stats(): HasOne
    {
        return $this->hasOne(ProductCategoryStats::class);
    }

    public function salesIntervals(): HasOne
    {
        return $this->hasOne(ProductCategorySalesIntervals::class);
    }

    public function parent(): MorphTo
    {
        return $this->morphTo();
    }

    public function departments(): MorphMany
    {
        return $this->morphMany(ProductCategory::class, 'parent');
    }

    public function products(): HasMany
    {
        return match ($this->type) {
            ProductCategoryTypeEnum::DEPARTMENT => $this->hasMany(Product::class, 'department_id'),
            ProductCategoryTypeEnum::FAMILY     => $this->hasMany(Product::class, 'family_id'),
            default                             => null
        };

    }
}
