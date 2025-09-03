<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 03 May 2023 11:42:04 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\Goods;

use App\Enums\Goods\TradeUnit\TradeUnitStatusEnum;
use App\Models\Catalogue\Product;
use App\Models\Helpers\Barcode;
use App\Models\Helpers\Brand;
use App\Models\Helpers\Media;
use App\Models\Helpers\Tag;
use App\Models\Inventory\OrgStock;
use App\Models\SupplyChain\SupplierProduct;
use App\Models\SysAdmin\Group;
use App\Models\Traits\HasAttachments;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasImage;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Spatie\Translatable\HasTranslations;

/**
 * App\Models\Goods\TradeUnit
 *
 * @property int $id
 * @property int $group_id
 * @property string $slug
 * @property string $code
 * @property string|null $name
 * @property string|null $description
 * @property int|null $barcode_id
 * @property Barcode|null $barcode
 * @property int|null $net_weight (grams)
 * @property int|null $gross_weight incl packing (grams)
 * @property int|null $marketing_weight to be shown in website (grams)
 * @property array<array-key, mixed>|null $marketing_dimensions
 * @property float|null $volume in cubic meters
 * @property string|null $type unit type
 * @property int|null $image_id
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $fetched_at
 * @property \Illuminate\Support\Carbon|null $last_fetched_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $source_slug
 * @property string|null $source_id
 * @property array<array-key, mixed> $sources
 * @property TradeUnitStatusEnum $status
 * @property string|null $anomality_status
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
 * @property array<array-key, mixed>|null $name_i8n
 * @property array<array-key, mixed>|null $description_i8n
 * @property array<array-key, mixed>|null $description_title_i8n
 * @property array<array-key, mixed>|null $description_extra_i8n
 * @property string|null $description_title
 * @property string|null $description_extra
 * @property string|null $cost_price
 * @property-read MediaCollection<int, Media> $attachments
 * @property-read Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read Media|null $backImage
 * @property-read Collection<int, Barcode> $barcodes
 * @property-read Media|null $bottomImage
 * @property-read Collection<int, Brand> $brands
 * @property-read Media|null $frontImage
 * @property-read Group $group
 * @property-read Media|null $image
 * @property-read MediaCollection<int, Media> $images
 * @property-read Collection<int, \App\Models\Goods\Ingredient> $ingredients
 * @property-read Media|null $leftImage
 * @property-read MediaCollection<int, Media> $media
 * @property-read Collection<int, OrgStock> $orgStocks
 * @property-read Collection<int, Product> $products
 * @property-read Media|null $rightImage
 * @property-read Media|null $seoImage
 * @property-read Media|null $sizeComparisonImage
 * @property-read \App\Models\Goods\TradeUnitStats|null $stats
 * @property-read Collection<int, \App\Models\Goods\Stock> $stocks
 * @property-read Collection<int, SupplierProduct> $supplierProducts
 * @property-read Collection<int, Tag> $tags
 * @property-read Media|null $threeQuarterImage
 * @property-read Media|null $topImage
 * @property-read mixed $translations
 * @method static \Database\Factories\Goods\TradeUnitFactory factory($count = null, $state = [])
 * @method static Builder<static>|TradeUnit newModelQuery()
 * @method static Builder<static>|TradeUnit newQuery()
 * @method static Builder<static>|TradeUnit onlyTrashed()
 * @method static Builder<static>|TradeUnit query()
 * @method static Builder<static>|TradeUnit whereJsonContainsLocale(string $column, string $locale, ?mixed $value, string $operand = '=')
 * @method static Builder<static>|TradeUnit whereJsonContainsLocales(string $column, array $locales, ?mixed $value, string $operand = '=')
 * @method static Builder<static>|TradeUnit whereLocale(string $column, string $locale)
 * @method static Builder<static>|TradeUnit whereLocales(string $column, array $locales)
 * @method static Builder<static>|TradeUnit withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|TradeUnit withoutTrashed()
 * @mixin Eloquent
 */
class TradeUnit extends Model implements HasMedia, Auditable
{
    use SoftDeletes;
    use HasSlug;
    use HasImage;
    use HasFactory;
    use HasHistory;
    use HasAttachments;
    use HasTranslations;


    public array $translatable = ['name_i8n', 'description_i8n', 'description_title_i8n', 'description_extra_i8n'];

    protected $casts = [
        'status'               => TradeUnitStatusEnum::class,
        'data'                 => 'array',
        'marketing_dimensions' => 'array',
        'sources'              => 'array',
        'fetched_at'           => 'datetime',
        'last_fetched_at'      => 'datetime',
    ];

    protected $attributes = [
        'data'                 => '{}',
        'marketing_dimensions' => '{}',
        'sources'              => '{}',
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
        'description',
        'barcode',
        'gross_weight',
        'net_weight',
        'marketing_dimensions',
        'volume',
        'type',
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('code')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function stocks(): MorphToMany
    {
        return $this->morphedByMany(Stock::class, 'model', 'model_has_trade_units')->withPivot(['quantity']);
    }

    public function products(): MorphToMany
    {
        return $this->morphedByMany(Product::class, 'model', 'model_has_trade_units');
    }

    public function orgStocks(): MorphToMany
    {
        return $this->morphedByMany(OrgStock::class, 'model', 'model_has_trade_units');
    }

    public function supplierProducts(): MorphToMany
    {
        return $this->morphedByMany(SupplierProduct::class, 'model', 'model_has_trade_units');
    }

    public function barcode(): BelongsTo
    {
        return $this->belongsTo(Barcode::class);
    }

    public function brands(): MorphToMany
    {
        return $this->morphToMany(Brand::class, 'model', 'model_has_brands');
    }

    public function brand(): ?Brand
    {
        /** @var Brand $brand */
        $brand = $this->brands()->first();
        return $brand;
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(
            Tag::class,
            'model',
            'model_has_tags'
        )->withTimestamps();
    }

    public function barcodes(): MorphToMany
    {
        return $this->morphToMany(Barcode::class, 'model', 'model_has_barcodes')
            ->withPivot('status', 'withdrawn_at', 'type')
            ->withTimestamps();
    }

    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class, 'trade_unit_has_ingredients')->withTimestamps()
            ->withPivot(
                'prefix',
                'suffix',
                'notes',
                'concentration',
                'purity',
                'percentage',
                'aroma'
            );
    }

    public function stats(): HasOne
    {
        return $this->hasOne(TradeUnitStats::class);
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
        return $this->hasOne(Media::class, 'id', 'top_image_id');
    }

    public function sizeComparisonImage(): HasOne
    {
        return $this->hasOne(Media::class, 'id', 'size_comparison_image_id');
    }


}
