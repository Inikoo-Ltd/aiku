<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Dec 2024 02:15:29 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Masters;

use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Goods\Stock;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Media;
use App\Models\SysAdmin\Group;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasImage;
use App\Models\Traits\HasUniversalSearch;
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
 * @property int|null $master_shop_id
 * @property int|null $master_family_id
 * @property int|null $master_sub_department_id
 * @property int|null $master_department_id
 * @property MasterAssetTypeEnum $type
 * @property bool $is_main
 * @property bool $status
 * @property string $slug
 * @property string $code
 * @property string|null $name
 * @property string|null $description
 * @property numeric|null $price price per outer in grp currency
 * @property string $units
 * @property string|null $unit
 * @property array<array-key, mixed> $data
 * @property int|null $gross_weight outer weight including packing, grams
 * @property int|null $marketing_weight to be shown in website, grams
 * @property string|null $barcode mirror from trade_unit
 * @property numeric|null $rrp RRP per outer grp currency
 * @property int|null $image_id
 * @property numeric $variant_ratio
 * @property bool $variant_is_visible
 * @property int|null $main_master_asset_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $fetched_at
 * @property \Illuminate\Support\Carbon|null $last_fetched_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $source_id
 * @property array<array-key, mixed>|null $name_i8n
 * @property array<array-key, mixed>|null $description_i8n
 * @property array<array-key, mixed>|null $description_title_i8n
 * @property array<array-key, mixed>|null $description_extra_i8n
 * @property bool $is_single_trade_unit Indicates if the master asset has a single trade unit
 * @property bool $in_process
 * @property bool $mark_for_discontinued
 * @property string|null $mark_for_discontinued_at
 * @property string|null $discontinued_at
 * @property string|null $cost_price_ratio
 * @property int|null $front_image_id
 * @property int|null $34_image_id
 * @property int|null $left_image_id
 * @property int|null $right_image_id
 * @property int|null $back_image_id
 * @property int|null $top_image_id
 * @property int|null $bottom_image_id
 * @property int|null $size_comparison_image_id
 * @property string|null $video_url
 * @property int|null $lifestyle_image_id
 * @property bool|null $bucket_images images following the buckets
 * @property string|null $description_title
 * @property string|null $description_extra
 * @property int|null $art1_image_id
 * @property int|null $art2_image_id
 * @property int|null $art3_image_id
 * @property int|null $art4_image_id
 * @property int|null $art5_image_id
 * @property array<array-key, mixed>|null $marketing_dimensions
 * @property MasterAssetStocksStatusEnum|null $stocks_status
 * @property MasterAssetProductsStatusEnum|null $products_status
 * @property-read Media|null $art1Image
 * @property-read Media|null $art2Image
 * @property-read Media|null $art3Image
 * @property-read Media|null $art4Image
 * @property-read Media|null $art5Image
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read Media|null $backImage
 * @property-read Media|null $bottomImage
 * @property-read Media|null $frontImage
 * @property-read Group $group
 * @property-read Media|null $image
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, Media> $images
 * @property-read Media|null $leftImage
 * @property-read Media|null $lifestyleImage
 * @property-read MasterAsset|null $mainMasterProduct
 * @property-read \App\Models\Masters\MasterProductCategory|null $masterDepartment
 * @property-read \App\Models\Masters\MasterProductCategory|null $masterFamily
 * @property-read \Illuminate\Database\Eloquent\Collection<int, MasterAsset> $masterProductVariants
 * @property-read \App\Models\Masters\MasterShop|null $masterShop
 * @property-read \App\Models\Masters\MasterProductCategory|null $masterSubDepartment
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, Media> $media
 * @property-read \App\Models\Masters\MasterAssetOrderingIntervals|null $orderingIntervals
 * @property-read \App\Models\Masters\MasterAssetOrderingStats|null $orderingStats
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Product> $products
 * @property-read Media|null $rightImage
 * @property-read \App\Models\Masters\MasterAssetSalesIntervals|null $salesIntervals
 * @property-read Media|null $seoImage
 * @property-read Media|null $sizeComparisonImage
 * @property-read \App\Models\Masters\MasterAssetStats|null $stats
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Stock> $stocks
 * @property-read Media|null $threeQuarterImage
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Masters\MasterAssetTimeSeries> $timeSeries
 * @property-read Media|null $topImage
 * @property-read \Illuminate\Database\Eloquent\Collection<int, TradeUnit> $tradeUnits
 * @property-read mixed $translations
 * @property-read \App\Models\Helpers\UniversalSearch|null $universalSearch
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterAsset newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterAsset newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterAsset onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterAsset query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterAsset whereJsonContainsLocale(string $column, string $locale, ?mixed $value, string $operand = '=')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterAsset whereJsonContainsLocales(string $column, array $locales, ?mixed $value, string $operand = '=')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterAsset whereLocale(string $column, string $locale)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterAsset whereLocales(string $column, array $locales)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterAsset withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterAsset withoutTrashed()
 * @mixin \Eloquent
 */
class MasterAsset extends Model implements Auditable, HasMedia
{
    use SoftDeletes;
    use HasSlug;
    use HasUniversalSearch;
    use HasHistory;
    use HasImage;
    use HasTranslations;

    public array $translatable = ['name_i8n', 'description_i8n', 'description_title_i8n', 'description_extra_i8n'];

    protected $guarded = [];

    protected $casts = [
        'type'                 => MasterAssetTypeEnum::class,
        'marketing_dimensions' => 'array',
        'variant_ratio'        => 'decimal:3',
        'price'                => 'decimal:2',
        'rrp'                  => 'decimal:2',
        'data'                 => 'array',
        'status'               => 'boolean',
        'variant_is_visible'   => 'boolean',
        'fetched_at'           => 'datetime',
        'last_fetched_at'      => 'datetime',
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
        'status',
        'state',
        'price',
        'rrp',
        'unit',
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
                $suffix = $this->masterShop?->code;
                if (!$suffix) {
                    $suffix = $this->group->code;
                }

                return $this->code.'-'.$suffix;
            })
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->slugsShouldBeNoLongerThan(128);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function masterShop(): BelongsTo
    {
        return $this->belongsTo(MasterShop::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'master_product_id');
    }

    public function masterProductVariants(): HasMany
    {
        return $this->hasMany(MasterAsset::class, 'main_master_product_id');
    }

    public function mainMasterProduct(): BelongsTo
    {
        return $this->belongsTo(MasterAsset::class, 'main_master_product_id');
    }

    public function masterDepartment(): BelongsTo
    {
        return $this->belongsTo(MasterProductCategory::class, 'master_department_id');
    }

    public function masterSubDepartment(): BelongsTo
    {
        return $this->belongsTo(MasterProductCategory::class, 'master_sub_department_id');
    }

    public function masterFamily(): BelongsTo
    {
        return $this->belongsTo(MasterProductCategory::class, 'master_family_id');
    }


    public function stats(): HasOne
    {
        return $this->hasOne(MasterAssetStats::class);
    }

    public function salesIntervals(): HasOne
    {
        return $this->hasOne(MasterAssetSalesIntervals::class);
    }

    public function orderingStats(): HasOne
    {
        return $this->hasOne(MasterAssetOrderingStats::class);
    }

    public function orderingIntervals(): HasOne
    {
        return $this->hasOne(MasterAssetOrderingIntervals::class);
    }

    public function timeSeries(): HasMany
    {
        return $this->hasMany(MasterAssetTimeSeries::class);
    }

    public function stocks(): BelongsToMany
    {
        return $this->belongsToMany(
            Stock::class,
            'master_asset_has_stocks',
        )->withPivot(['quantity', 'notes'])->withTimestamps();
    }

    public function tradeUnits(): MorphToMany
    {
        return $this->morphToMany(TradeUnit::class, 'model', 'model_has_trade_units')->withPivot(['quantity', 'notes'])->withTimestamps();
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

    public function lifestyleImage(): HasOne
    {
        return $this->hasOne(Media::class, 'id', 'lifestyle_image_id');
    }

    public function bottomImage(): HasOne
    {
        return $this->hasOne(Media::class, 'id', 'bottom_image_id');
    }

    public function sizeComparisonImage(): HasOne
    {
        return $this->hasOne(Media::class, 'id', 'size_comparison_image_id');
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
