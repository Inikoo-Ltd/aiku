<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Thu, 20 Oct 2022 18:49:22 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Models\Catalogue;

use App\Enums\Catalogue\HealthRankEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Discounts\Offer\OfferStateEnum;
use App\Models\Discounts\Offer;
use App\Models\Goods\TradeUnitFamily;
use App\Models\Helpers\Media;
use App\Models\Masters\MasterProductCategory;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasImage;
use App\Models\Traits\InShop;
use App\Models\Web\ModelHasContent;
use App\Models\Web\Webpage;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as LaravelCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use App\Models\Traits\HasSearch;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Spatie\Translatable\HasTranslations;

/**
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
 * @property Carbon|null $activated_at
 * @property Carbon|null $discontinuing_at
 * @property Carbon|null $discontinued_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $fetched_at
 * @property Carbon|null $last_fetched_at
 * @property Carbon|null $deleted_at
 * @property string|null $delete_comment
 * @property string|null $source_department_id
 * @property string|null $source_family_id
 * @property bool $follow_master
 * @property bool $show_in_website
 * @property int|null $webpage_id
 * @property string|null $url
 * @property array<array-key, mixed> $web_images
 * @property int|null $top_seller
 * @property string|null $description_title
 * @property string|null $description_extra
 * @property array<array-key, mixed>|null $name_i8n
 * @property array<array-key, mixed>|null $description_i8n
 * @property array<array-key, mixed>|null $description_title_i8n
 * @property array<array-key, mixed>|null $description_extra_i8n
 * @property numeric|null $cost_price_ratio
 * @property int|null $lifestyle_image_id
 * @property bool|null $bucket_images images following the buckets
 * @property bool|null $is_name_reviewed
 * @property bool|null $is_description_title_reviewed
 * @property bool|null $is_description_reviewed
 * @property bool|null $is_description_extra_reviewed
 * @property array<array-key, mixed>|null $offers_data
 * @property bool|null $is_for_sale
 * @property string|null $not_for_sale_since
 * @property HealthRankEnum|null $health_rank
 * @property string|null $desc_video_url
 * @property int|null $desc_art1
 * @property int|null $desc_art2
 * @property int|null $desc_art3
 * @property int|null $desc_art4
 * @property int|null $desc_art5
 * @property int|null $extra_desc_art1
 * @property bool|null $mismatch_with_master_detected
 * @property int|null $extra_desc_art2
 * @property int|null $extra_desc_art3
 * @property int|null $extra_desc_art4
 * @property-read LaravelCollection<int, \App\Models\Helpers\Audit> $audits
 * @property-read LaravelCollection<int, ProductCategory> $children
 * @property-read LaravelCollection<int, \App\Models\Catalogue\Collection> $collections
 * @property-read LaravelCollection<int, \App\Models\Catalogue\Collection> $containedByCollections
 * @property-read LaravelCollection<int, ModelHasContent> $contents
 * @property-read ProductCategory|null $department
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
 * @property-read MasterProductCategory|null $masterProductCategory
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, Media> $media
 * @property-read Organisation $organisation
 * @property-read ProductCategory|null $parent
 * @property-read LaravelCollection<int, ProductCategory> $relatedProductCategories
 * @property-read LaravelCollection<int, \App\Models\Catalogue\Product> $relatedProducts
 * @property-read Media|null $seoImage
 * @property-read \App\Models\Catalogue\Shop|null $shop
 * @property-read \App\Models\Catalogue\ProductCategoryStats|null $stats
 * @property-read ProductCategory|null $subDepartment
 * @property-read LaravelCollection<int, \App\Models\Catalogue\ProductCategoryTimeSeries> $timeSeries
 * @property-read mixed $translations
 * @property-read Webpage|null $webpage
 * @property-read LaravelCollection<int, Webpage> $webpages
 * @method static \Database\Factories\Catalogue\ProductCategoryFactory factory($count = null, $state = [])
 * @method static Builder<static>|ProductCategory newModelQuery()
 * @method static Builder<static>|ProductCategory newQuery()
 * @method static Builder<static>|ProductCategory onlyTrashed()
 * @method static Builder<static>|ProductCategory query()
 * @method static Builder<static>|ProductCategory whereJsonContainsLocale(string $column, string $locale, ?mixed $value, string $operand = '=')
 * @method static Builder<static>|ProductCategory whereJsonContainsLocales(string $column, array $locales, ?mixed $value, string $operand = '=')
 * @method static Builder<static>|ProductCategory whereLocale(string $column, string $locale)
 * @method static Builder<static>|ProductCategory whereLocales(string $column, array $locales)
 * @method static Builder<static>|ProductCategory withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|ProductCategory withoutTrashed()
 * @mixin Eloquent
 */
class ProductCategory extends Model implements Auditable, HasMedia
{
    use HasSlug;
    use SoftDeletes;
    use HasFactory;
    use HasHistory;
    use InShop;
    use HasImage;
    use HasTranslations;
    use HasSearch;

    protected $guarded = [];

    public array $translatable = ['name_i8n', 'description_i8n', 'description_title_i8n', 'description_extra_i8n'];

    protected $casts = [
        'data'                          => 'array',
        'faq'                           => 'array',
        'web_images'                    => 'array',
        'health_rank'                   => HealthRankEnum::class,
        'state'                         => ProductCategoryStateEnum::class,
        'type'                          => ProductCategoryTypeEnum::class,
        'activated_at'                  => 'datetime',
        'discontinuing_at'              => 'datetime',
        'discontinued_at'               => 'datetime',
        'fetched_at'                    => 'datetime',
        'last_fetched_at'               => 'datetime',
        'offers_data'                   => 'array',
        'mismatch_with_master_detected' => 'boolean',
    ];

    protected $attributes = [
        'data'        => '{}',
        'faq'         => '{}',
        'web_images'  => '{}',
        'offers_data' => '{}',
    ];

    public function toSearchableArray(): array
    {
        return [
            'id'                => (string)$this->id,
            'shop_id'           => $this->shop_id,
            'type'              => $this->type->value,
            'code'              => $this->code,
            'name'              => (string)$this->name,
            'description'       => (string)$this->description,
            'description_extra' => (string)$this->description_extra,
            'state'             => $this->state->value,
            'created_at'   => is_string($this->created_at) ? Carbon::parse($this->created_at)->timestamp : $this->created_at->timestamp,
        ];
    }

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

    public function contents(): MorphMany
    {
        return $this->morphMany(ModelHasContent::class, 'model');
    }


    public function stats(): HasOne
    {
        return $this->hasOne(ProductCategoryStats::class);
    }

    public function timeSeries(): HasMany
    {
        return $this->hasMany(ProductCategoryTimeSeries::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'department_id');
    }


    public function subDepartment(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'sub_department_id');
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

    public function getSubDepartments(): LaravelCollection
    {
        return $this->children()->where('type', ProductCategoryTypeEnum::SUB_DEPARTMENT)->get();
    }

    public function getProducts(): LaravelCollection
    {
        return match ($this->type) {
            ProductCategoryTypeEnum::DEPARTMENT => Product::where('department_id', $this->id)->get(),
            ProductCategoryTypeEnum::FAMILY => Product::where('family_id', $this->id)->get(),
            ProductCategoryTypeEnum::SUB_DEPARTMENT => Product::where('sub_department_id', $this->id)->get(),
        };
    }

    public function getProductsDistinctVariant(): LaravelCollection // This is to fetch non-variant products. If it's a variant, it will fetch only the leader.
    {
        $column = match ($this->type) {
            ProductCategoryTypeEnum::DEPARTMENT => 'department_id',
            ProductCategoryTypeEnum::FAMILY => 'family_id',
            ProductCategoryTypeEnum::SUB_DEPARTMENT => 'sub_department_id',
        };

        return Product::where($column, $this->id)
            ->where(function ($query) {
                $query
                    ->where('products.is_minion_variant', false)
                    ->orWhere('is_variant_leader', true);
            })
            ->get();
    }

    public function getActiveProducts(): LaravelCollection
    {
        return match ($this->type) {
            ProductCategoryTypeEnum::DEPARTMENT => Product::where('department_id', $this->id)
                ->where('is_for_sale', true)
                ->where('state', '!=', ProductStateEnum::DISCONTINUED->value)
                ->get(),
            ProductCategoryTypeEnum::FAMILY => Product::where('family_id', $this->id)
                ->where('is_for_sale', true)
                ->where('state', '!=', ProductStateEnum::DISCONTINUED->value)
                ->get(),
            ProductCategoryTypeEnum::SUB_DEPARTMENT => Product::where('sub_department_id', $this->id)
                ->where('is_for_sale', true)
                ->where('state', '!=', ProductStateEnum::DISCONTINUED->value)
                ->get(),
        };
    }

    public function collections(): MorphToMany
    {
        return $this->morphToMany(Collection::class, 'model', 'model_has_collections')->withTimestamps();
    }

    public function containedByCollections(): MorphToMany
    {
        return $this->morphToMany(Collection::class, 'model', 'collection_has_models')
            ->withTimestamps();
    }

    public function webpage(): MorphOne
    {
        $relation = $this->morphOne(Webpage::class, 'model');

        if ($this->type === ProductCategoryTypeEnum::DEPARTMENT) {
            $relation->where('url', $this->url);
        }

        return $relation;
    }

    public function webpages(): MorphMany
    {
        return $this->morphMany(Webpage::class, 'model');
    }

    public function masterProductCategory(): BelongsTo
    {
        return $this->belongsTo(MasterProductCategory::class);
    }

    public function getOffers(): HasMany
    {
        return $this->hasMany(Offer::class, 'trigger_id')
            ->where('trigger_type', class_basename(ProductCategory::class));
    }

    public function getGROffer(): HasOne
    {
        return $this->hasOne(Offer::class, 'trigger_id')
            ->where('trigger_type', class_basename(ProductCategory::class))
            ->where('state', OfferStateEnum::ACTIVE)
            ->where('type', 'Category Quantity Ordered Order Interval');
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

    public function relatedProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_category_has_related_products')
            ->withTimestamps();
    }

    public function relatedProductCategories(): BelongsToMany
    {
        return $this->belongsToMany(ProductCategory::class, 'product_category_has_related_product_categories', 'product_category_id', 'related_product_category_id')
            ->orderByPivot('position')
            ->withPivot('id', 'position')
            ->withTimestamps();
    }

    public function tradeUnitFamily(): BelongsTo
    {
        return $this->belongsTo(TradeUnitFamily::class, 'trade_unit_family_id', 'id');
    }
}
