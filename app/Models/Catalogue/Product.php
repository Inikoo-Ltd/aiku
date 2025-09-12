<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 08 Apr 2024 09:52:43 Central Indonesia Time, Bali Office, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Catalogue;

use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Enums\Catalogue\Product\ProductTradeConfigEnum;
use App\Enums\Catalogue\Product\ProductUnitRelationshipType;
use App\Models\CRM\BackInStockReminder;
use App\Models\CRM\Customer;
use App\Models\CRM\Favourite;
use App\Models\Dropshipping\Portfolio;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Brand;
use App\Models\Helpers\Currency;
use App\Models\Helpers\Media;
use App\Models\Helpers\Tag;
use App\Models\Inventory\OrgStock;
use App\Models\Masters\MasterAsset;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasImage;
use App\Models\Traits\HasUniversalSearch;
use App\Models\Web\ModelHasContent;
use App\Models\Web\Webpage;
use App\Models\Web\WebpageHasProduct;
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
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Spatie\Translatable\HasTranslations;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int|null $shop_id
 * @property int|null $asset_id
 * @property int|null $family_id
 * @property int|null $sub_department_id
 * @property int|null $department_id
 * @property bool $is_main
 * @property ProductStatusEnum $status
 * @property ProductStateEnum $state
 * @property ProductTradeConfigEnum $trade_config
 * @property string $slug
 * @property string $code
 * @property string|null $name
 * @property string|null $description
 * @property numeric|null $price
 * @property string $units
 * @property string $unit
 * @property array<array-key, mixed> $data
 * @property array<array-key, mixed> $settings
 * @property int $currency_id
 * @property int|null $current_historic_asset_id
 * @property int|null $gross_weight outer weight including packing, grams
 * @property int|null $marketing_weight to be shown in website, grams
 * @property string|null $barcode mirror from trade_unit
 * @property numeric|null $rrp RRP per outer
 * @property int|null $image_id
 * @property ProductUnitRelationshipType|null $unit_relationship_type
 * @property int|null $available_quantity outer available quantity for sale
 * @property numeric $variant_ratio
 * @property bool $variant_is_visible
 * @property int|null $main_product_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $fetched_at
 * @property \Illuminate\Support\Carbon|null $last_fetched_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $source_id
 * @property string|null $historic_source_id
 * @property bool $is_for_sale For-sale products including out of stock
 * @property int|null $exclusive_for_customer_id
 * @property int|null $webpage_id
 * @property string|null $url
 * @property array<array-key, mixed> $web_images
 * @property int|null $top_seller
 * @property string|null $description_title
 * @property string|null $description_extra
 * @property string|null $un_number
 * @property string|null $un_class
 * @property string|null $packing_group
 * @property string|null $proper_shipping_name
 * @property string|null $hazard_identification_number
 * @property string|null $gpsr_manufacturer
 * @property string|null $gpsr_eu_responsible
 * @property string|null $gpsr_warnings
 * @property string|null $gpsr_manual
 * @property string|null $gpsr_class_category_danger
 * @property string|null $gpsr_class_languages
 * @property bool $pictogram_toxic
 * @property bool $pictogram_corrosive
 * @property bool $pictogram_explosive
 * @property bool $pictogram_flammable
 * @property bool $pictogram_gas
 * @property bool $pictogram_environment
 * @property bool $pictogram_health
 * @property bool $pictogram_oxidising
 * @property bool $pictogram_danger
 * @property array<array-key, mixed>|null $marketing_dimensions
 * @property int|null $front_image_id
 * @property int|null $34_image_id
 * @property int|null $left_image_id
 * @property int|null $right_image_id
 * @property int|null $back_image_id
 * @property int|null $top_image_id
 * @property int|null $bottom_image_id
 * @property int|null $size_comparison_image_id
 * @property string|null $video_url
 * @property string|null $cpnp_number
 * @property string|null $country_of_origin
 * @property string|null $tariff_code
 * @property string|null $duty_rate
 * @property string|null $hts_us
 * @property string|null $marketing_ingredients
 * @property string|null $price_updated_at
 * @property string|null $available_quantity_updated_at
 * @property string|null $images_updated_at
 * @property string|null $unit_price price per unit
 * @property array<array-key, mixed>|null $name_i8n
 * @property array<array-key, mixed>|null $description_i8n
 * @property array<array-key, mixed>|null $description_title_i8n
 * @property array<array-key, mixed>|null $description_extra_i8n
 * @property bool $is_single_trade_unit Indicates if the product has a single trade unit
 * @property int|null $master_product_id
 * @property string|null $mark_for_discontinued_at
 * @property string|null $discontinued_at
 * @property string|null $cost_price_ratio
 * @property int|null $lifestyle_image_id
 * @property bool|null $bucket_images images following the buckets
 * @property-read Media|null $art1Image
 * @property-read Media|null $art2Image
 * @property-read Media|null $art3Image
 * @property-read Media|null $art4Image
 * @property-read Media|null $art5Image
 * @property-read \App\Models\Catalogue\Asset|null $asset
 * @property-read LaravelCollection<int, \App\Models\Helpers\Audit> $audits
 * @property-read Media|null $backImage
 * @property-read LaravelCollection<int, BackInStockReminder> $backInStockReminders
 * @property-read Media|null $bottomImage
 * @property-read LaravelCollection<int, \App\Models\Catalogue\Collection> $collections
 * @property-read LaravelCollection<int, \App\Models\Catalogue\Collection> $containedByCollections
 * @property-read LaravelCollection<int, ModelHasContent> $contents
 * @property-read Currency $currency
 * @property-read \App\Models\Catalogue\HistoricAsset|null $currentHistoricProduct
 * @property-read \App\Models\Catalogue\ProductCategory|null $department
 * @property-read Customer|null $exclusiveForCustomer
 * @property-read \App\Models\Catalogue\ProductCategory|null $family
 * @property-read LaravelCollection<int, Favourite> $favourites
 * @property-read Media|null $frontImage
 * @property-read Group $group
 * @property-read \App\Models\Catalogue\HistoricAsset|null $historicAsset
 * @property-read LaravelCollection<int, \App\Models\Catalogue\HistoricAsset> $historicAssets
 * @property-read Media|null $image
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, Media> $images
 * @property-read Media|null $leftImage
 * @property-read Media|null $lifestyleImage
 * @property-read Product|null $mainProduct
 * @property-read MasterAsset|null $masterProduct
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, Media> $media
 * @property-read LaravelCollection<int, OrgStock> $orgStocks
 * @property-read Organisation $organisation
 * @property-read LaravelCollection<int, Portfolio> $portfolios
 * @property-read LaravelCollection<int, Product> $productVariants
 * @property-read Media|null $rightImage
 * @property-read Media|null $seoImage
 * @property-read \App\Models\Catalogue\Shop|null $shop
 * @property-read Media|null $sizeComparisonImage
 * @property-read \App\Models\Catalogue\ProductStats|null $stats
 * @property-read \App\Models\Catalogue\ProductCategory|null $subDepartment
 * @property-read Media|null $threeQuarterImage
 * @property-read Media|null $topImage
 * @property-read LaravelCollection<int, TradeUnit> $tradeUnits
 * @property-read mixed $translations
 * @property-read \App\Models\Helpers\UniversalSearch|null $universalSearch
 * @property-read Webpage|null $webpage
 * @property-read LaravelCollection<int, WebpageHasProduct> $webpageHasProducts
 * @method static \Database\Factories\Catalogue\ProductFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereJsonContainsLocale(string $column, string $locale, ?mixed $value, string $operand = '=')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereJsonContainsLocales(string $column, array $locales, ?mixed $value, string $operand = '=')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereLocale(string $column, string $locale)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereLocales(string $column, array $locales)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product withoutTrashed()
 * @mixin \Eloquent
 */
class Product extends Model implements Auditable, HasMedia
{
    use SoftDeletes;
    use HasSlug;
    use HasUniversalSearch;
    use InAssetModel;
    use HasHistory;
    use HasFactory;
    use HasImage;
    use HasTranslations;

    protected $guarded = [];

    public array $translatable = ['name_i8n', 'description_i8n', 'description_title_i8n', 'description_extra_i8n'];


    protected $casts = [
        'variant_ratio'          => 'decimal:3',
        'price'                  => 'decimal:2',
        'rrp'                    => 'decimal:2',
        'data'                   => 'array',
        'settings'               => 'array',
        'web_images'             => 'array',
        'marketing_dimensions'   => 'array',
        'variant_is_visible'     => 'boolean',
        'state'                  => ProductStateEnum::class,
        'status'                 => ProductStatusEnum::class,
        'trade_config'           => ProductTradeConfigEnum::class,
        'unit_relationship_type' => ProductUnitRelationshipType::class,
        'fetched_at'             => 'datetime',
        'last_fetched_at'        => 'datetime'
    ];

    protected $attributes = [
        'data'                 => '{}',
        'settings'             => '{}',
        'web_images'           => '{}',
        'marketing_dimensions' => '{}',
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
        'status',
        'state',
        'trade_config',
        'price',
        'rrp',
        'currency_id',
        'units',
        'unit',
        'is_auto_assign',
        'auto_assign_trigger',
        'auto_assign_subject',
        'auto_assign_subject_type',
        'auto_assign_status',
        'is_main',
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
        return $this->hasOne(ProductStats::class);
    }

    public function contents(): MorphMany
    {
        return $this->morphMany(ModelHasContent::class, 'model');
    }

    public function orgStocks(): BelongsToMany
    {
        return $this->belongsToMany(
            OrgStock::class,
            'product_has_org_stocks',
        )->withPivot(['quantity', 'notes'])->withTimestamps();
    }

    public function tradeUnits(): MorphToMany
    {
        return $this->morphToMany(TradeUnit::class, 'model', 'model_has_trade_units')->withPivot(['quantity', 'notes'])->withTimestamps();
    }

    public function productVariants(): HasMany
    {
        return $this->hasMany(Product::class, 'main_product_id');
    }

    public function mainProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'main_product_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'department_id');
    }

    public function subDepartment(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'sub_department_id');
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'family_id');
    }

    public function collections(): MorphToMany
    {
        return $this->morphToMany(Collection::class, 'model', 'model_has_collections')->withTimestamps();
    }

    public function webpage(): MorphOne
    {
        return $this->morphOne(Webpage::class, 'model');
    }

    public function portfolios(): MorphMany
    {
        return $this->morphMany(Portfolio::class, 'item');
    }

    public function favourites(): HasMany
    {
        return $this->hasMany(Favourite::class);
    }

    public function webpageHasProducts(): HasMany
    {
        return $this->hasMany(WebpageHasProduct::class);
    }

    public function backInStockReminders(): HasMany
    {
        return $this->hasMany(BackInStockReminder::class);
    }

    public function tradeUnitTagsViaTradeUnits(): LaravelCollection
    {
        return Tag::whereHas('tradeUnits', function ($query) {
            $query->whereIn('trade_units.id', $this->tradeUnits()->pluck('trade_units.id'));
        })->get();
    }

    public function getBrand(): ?Brand
    {
        return Brand::whereHas('tradeUnits', function ($query) {
            $query->whereIn('trade_units.id', $this->tradeUnits()->pluck('trade_units.id'));
        })->first();
    }

    public function exclusiveForCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function containedByCollections(): MorphToMany
    {
        return $this->morphToMany(Collection::class, 'model', 'collection_has_models')
            ->withTimestamps();
    }

    public function currentHistoricProduct(): HasOne
    {
        return $this->hasOne(HistoricAsset::class, 'id', 'current_historic_asset_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    public function masterProduct(): BelongsTo
    {
        return $this->belongsTo(MasterAsset::class, 'master_product_id');
    }

    public function getLuigiIdentity(): string
    {
        return $this->group_id . ':' . $this->organisation_id . ':' . $this->shop_id . ':' . $this->webpage?->website?->id . ':' . $this->webpage?->id;
    }

    public function frontImage(): HasOne
    {
        return $this->hasOne(Media::class, 'id', 'front_image_id');
    }

    public function threeQuarterImage(): HasOne
    {
        return $this->hasOne(Media::class, 'id', '34_image_id');
    }

    public function leftImage(): HasOne
    {
        return $this->hasOne(Media::class, 'id', 'left_image_id');
    }

    public function rightImage(): HasOne
    {
        return $this->hasOne(Media::class, 'id', 'right_image_id');
    }

    public function backImage(): HasOne
    {
        return $this->hasOne(Media::class, 'id', 'back_image_id');
    }

    public function topImage(): HasOne
    {
        return $this->hasOne(Media::class, 'id', 'top_image_id');
    }

    public function bottomImage(): HasOne
    {
        return $this->hasOne(Media::class, 'id', 'bottom_image_id');
    }

    public function sizeComparisonImage(): HasOne
    {
        return $this->hasOne(Media::class, 'id', 'size_comparison_image_id');
    }

    public function lifestyleImage(): HasOne
    {
        return $this->hasOne(Media::class, 'id', 'lifestyle_image_id');
    }

    public function art1Image(): HasOne
    {
        return $this->hasOne(Media::class, 'id', 'art1_image_id');
    }

    public function art2Image(): HasOne
    {
        return $this->hasOne(Media::class, 'id', 'art2_image_id');
    }

    public function art3Image(): HasOne
    {
        return $this->hasOne(Media::class, 'id', 'art3_image_id');
    }

    public function art4Image(): HasOne
    {
        return $this->hasOne(Media::class, 'id', 'art4_image_id');
    }

    public function art5Image(): HasOne
    {
        return $this->hasOne(Media::class, 'id', 'art5_image_id');
    }
}
