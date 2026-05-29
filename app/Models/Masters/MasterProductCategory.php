<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Dec 2024 02:15:29 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Masters;

use App\Enums\Catalogue\HealthRankEnum;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\ProductCategory;
use App\Models\Helpers\Media;
use App\Models\SysAdmin\Group;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasImage;
use App\Models\Traits\InGroup;
use Illuminate\Database\Eloquent\Collection as LaravelCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Spatie\Translatable\HasTranslations;

/**
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
 * @property array<array-key, mixed>|null $name_i8n
 * @property array<array-key, mixed>|null $description_i8n
 * @property array<array-key, mixed>|null $description_title_i8n
 * @property array<array-key, mixed>|null $description_extra_i8n
 * @property string|null $description_title
 * @property string|null $description_extra
 * @property bool $in_process
 * @property bool $mark_for_discontinued
 * @property string|null $mark_for_discontinued_at
 * @property \Illuminate\Support\Carbon|null $discontinued_at
 * @property numeric|null $cost_price_ratio
 * @property int|null $lifestyle_image_id
 * @property bool|null $bucket_images images following the buckets
 * @property array<array-key, mixed>|null $offers_data
 * @property bool|null $is_for_sale
 * @property string|null $not_for_sale_since
 * @property array<array-key, mixed>|null $web_images
 * @property bool $has_gr_vol_discount
 * @property bool $mismatch_detected One of master products under it has a mismatch trade unit data (picking quantity, linked trade unit) with one or more of its children product
 * @property HealthRankEnum|null $health_rank
 * @property string|null $desc_video_url
 * @property int|null $desc_art1
 * @property int|null $desc_art2
 * @property int|null $desc_art3
 * @property int|null $desc_art4
 * @property int|null $desc_art5
 * @property int|null $extra_desc_art1
 * @property int|null $extra_desc_art2
 * @property int|null $extra_desc_art3
 * @property int|null $extra_desc_art4
 * @property numeric|null $gr_vol_discount_percentage
 * @property int|null $gr_vol_discount_quantity
 * @property-read LaravelCollection<int, \App\Models\Helpers\Audit> $audits
 * @property-read LaravelCollection<int, MasterProductCategory> $children
 * @property-read Media|null $descArt1Image
 * @property-read Media|null $descArt2Image
 * @property-read Media|null $descArt3Image
 * @property-read Media|null $descArt4Image
 * @property-read Media|null $descArt5Image
 * @property-read Media|null $extraDescArt1Image
 * @property-read Media|null $extraDescArt2Image
 * @property-read Media|null $extraDescArt3Image
 * @property-read Media|null $extraDescArt4Image
 * @property-read array $translatable_columns_from
 * @property-read Group|null $group
 * @property-read Media|null $image
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, Media> $images
 * @property-read LaravelCollection<int, \App\Models\Masters\MasterCollection> $masterCollections
 * @property-read MasterProductCategory|null $masterDepartment
 * @property-read LaravelCollection<int, MasterProductCategory> $masterProductCategories
 * @property-read \App\Models\Masters\MasterShop|null $masterShop
 * @property-read MasterProductCategory|null $masterSubDepartment
 * @property-read LaravelCollection<int, \App\Models\Masters\MasterVariant> $masterVariant
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, Media> $media
 * @property-read MasterProductCategory|null $parent
 * @property-read LaravelCollection<int, ProductCategory> $productCategories
 * @property-read LaravelCollection<int, \App\Models\Masters\MasterAsset> $relatedMasterAssets
 * @property-read Media|null $seoImage
 * @property-read \App\Models\Masters\MasterProductCategoryStats|null $stats
 * @property-read LaravelCollection<int, \App\Models\Masters\MasterProductCategoryTimeSeries> $timeSeries
 * @property-read mixed $translations
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterProductCategory newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterProductCategory newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterProductCategory onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterProductCategory query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterProductCategory whereJsonContainsLocale(string $column, string $locale, ?mixed $value, string $operand = '=')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterProductCategory whereJsonContainsLocales(string $column, array $locales, ?mixed $value, string $operand = '=')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterProductCategory whereLocale(string $column, string $locale)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterProductCategory whereLocales(string $column, array $locales)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterProductCategory withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterProductCategory withoutTrashed()
 * @mixin \Eloquent
 */
class MasterProductCategory extends Model implements Auditable, HasMedia
{
    use HasSlug;
    use SoftDeletes;
    use HasHistory;
    use HasImage;
    use InGroup;
    use HasTranslations;

    protected $guarded = [];

    public array $translatable = ['name_i8n', 'description_i8n', 'description_title_i8n', 'description_extra_i8n'];

    protected $casts = [
        'data'            => 'array',
        'web_images'      => 'array',
        'type'            => MasterProductCategoryTypeEnum::class,
        'health_rank'     => HealthRankEnum::class,
        'status'          => 'boolean',
        'fetched_at'      => 'datetime',
        'last_fetched_at' => 'datetime',
        'discontinued_at' => 'datetime',
        'offers_data'     => 'array',
    ];

    protected $attributes = [
        'data'        => '{}',
        'offers_data' => '{}',
        'web_images'  => '{}',
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
        'description_title',
        'description_extra',
        'cost_price_ratio'
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

    public function productCategories(): HasMany
    {
        return $this->hasMany(ProductCategory::class, 'master_product_category_id');
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

    public function masterVariant(): HasMany
    {
        return $this->hasMany(MasterVariant::class, 'master_family_id');
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
            MasterProductCategoryTypeEnum::SUB_DEPARTMENT => $this->hasMany(MasterAsset::class, 'master_sub_department_id'),
            default => null
        };
    }

    public function masterShop(): BelongsTo
    {
        return $this->belongsTo(MasterShop::class);
    }

    public function masterCollections(): MorphToMany
    {
        return $this->morphToMany(MasterCollection::class, 'model', 'model_has_master_collections')->withTimestamps();
    }


    public function descArt1Image(): HasOne
    {
        return $this->hasOne(Media::class, 'id', 'desc_art1');
    }

    public function descArt2Image(): HasOne
    {
        return $this->hasOne(Media::class, 'id', 'desc_art2');
    }

    public function descArt3Image(): HasOne
    {
        return $this->hasOne(Media::class, 'id', 'desc_art3');
    }

    public function descArt4Image(): HasOne
    {
        return $this->hasOne(Media::class, 'id', 'desc_art4');
    }

    public function descArt5Image(): HasOne
    {
        return $this->hasOne(Media::class, 'id', 'desc_art5');
    }

    public function extraDescArt1Image(): HasOne
    {
        return $this->hasOne(Media::class, 'id', 'extra_desc_art1');
    }

    public function extraDescArt2Image(): HasOne
    {
        return $this->hasOne(Media::class, 'id', 'extra_desc_art2');
    }

    public function extraDescArt3Image(): HasOne
    {
        return $this->hasOne(Media::class, 'id', 'extra_desc_art3');
    }

    public function extraDescArt4Image(): HasOne
    {
        return $this->hasOne(Media::class, 'id', 'extra_desc_art4');
    }

    public function relatedMasterAssets(): BelongsToMany
    {
        return $this->belongsToMany(MasterAsset::class, 'master_product_category_has_related_assets')
            ->orderByPivot('position')
            ->withPivot('id', 'position')
            ->withTimestamps();
    }

    public function relatedMasterProductCategories(): BelongsToMany
    {
        return $this->belongsToMany(MasterProductCategory::class, 'master_product_category_has_related_product_categories', 'master_product_category_id', 'related_master_product_category_id')
            ->orderByPivot('position')
            ->withPivot('id', 'position')
            ->withTimestamps();
    }

}
