<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Dec 2024 12:06:14 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Goods;

use App\Enums\Goods\Stock\StockStateEnum;
use App\Enums\Goods\Stock\StockTradeUnitCompositionEnum;
use App\Models\Helpers\Barcode;
use App\Models\Helpers\Media;
use App\Models\Helpers\UniversalSearch;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\StockIntervals;
use App\Models\StockSalesInterval;
use App\Models\SupplyChain\SupplierProduct;
use App\Models\SysAdmin\Group;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasImage;
use App\Models\Traits\HasUniversalSearch;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * App\Models\SupplyChain\Stock
 *
 * @property int $id
 * @property int $group_id
 * @property string $slug
 * @property string $code
 * @property string|null $name
 * @property string|null $description
 * @property int|null $stock_family_id
 * @property StockTradeUnitCompositionEnum|null $trade_unit_composition
 * @property StockStateEnum $state
 * @property bool $sellable
 * @property bool $raw_material
 * @property int|null $units_per_pack units per pack
 * @property int|null $units_per_carton units per carton
 * @property string|null $unit_value
 * @property int|null $image_id
 * @property int|null $gross_weight package weight grams
 * @property array<array-key, mixed> $settings
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $activated_at
 * @property \Illuminate\Support\Carbon|null $discontinuing_at
 * @property \Illuminate\Support\Carbon|null $discontinued_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $fetched_at
 * @property \Illuminate\Support\Carbon|null $last_fetched_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $source_slug
 * @property string|null $source_id
 * @property array<array-key, mixed> $sources
 * @property-read Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read Collection<int, Barcode> $barcode
 * @property-read Group $group
 * @property-read Media|null $image
 * @property-read MediaCollection<int, Media> $images
 * @property-read StockIntervals|null $intervals
 * @property-read MediaCollection<int, Media> $media
 * @property-read Collection<int, OrgStock> $orgStocks
 * @property-read StockSalesInterval|null $salesIntervals
 * @property-read \App\Models\Goods\StockStats|null $stats
 * @property-read \App\Models\Goods\StockFamily|null $stockFamily
 * @property-read Collection<int, SupplierProduct> $supplierProducts
 * @property-read Collection<int, \App\Models\Goods\StockTimeSeries> $timeSeries
 * @property-read Collection<int, \App\Models\Goods\TradeUnit> $tradeUnits
 * @property-read UniversalSearch|null $universalSearch
 * @method static \Database\Factories\Goods\StockFactory factory($count = null, $state = [])
 * @method static Builder<static>|Stock newModelQuery()
 * @method static Builder<static>|Stock newQuery()
 * @method static Builder<static>|Stock onlyTrashed()
 * @method static Builder<static>|Stock query()
 * @method static Builder<static>|Stock withTrashed()
 * @method static Builder<static>|Stock withoutTrashed()
 * @mixin Eloquent
 */
class Stock extends Model implements HasMedia, Auditable
{
    use SoftDeletes;
    use HasSlug;
    use HasUniversalSearch;
    use HasImage;
    use HasFactory;
    use HasHistory;

    protected $casts = [
        'data'                   => 'array',
        'settings'               => 'array',
        'sources'                => 'array',
        'activated_at'           => 'datetime',
        'discontinuing_at'       => 'datetime',
        'discontinued_at'        => 'datetime',
        'state'                  => StockStateEnum::class,
        'trade_unit_composition' => StockTradeUnitCompositionEnum::class,
        'fetched_at'             => 'datetime',
        'last_fetched_at'        => 'datetime',
    ];

    protected $attributes = [
        'data'     => '{}',
        'settings' => '{}',
        'sources'  => '{}',
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
        'state',
        'description',
        'trade_unit_composition',
        'sellable',
        'raw_material',
        'units_per_pack',
        'units_per_carton',
        'activated_at',
        'discontinuing_at',
        'discontinued_at',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('code')
            ->doNotGenerateSlugsOnUpdate()
            ->saveSlugsTo('slug');
    }

    public function tradeUnits(): MorphToMany
    {
        return $this->morphToMany(
            TradeUnit::class,
            'model',
            'model_has_trade_units',
            'model_id',
            null,
            null,
            null,
            'trade_units',
        )
            ->withPivot(['quantity', 'notes'])
            ->withTimestamps();
    }

    public function orgStocks(): HasMany
    {
        return $this->hasMany(OrgStock::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function stats(): HasOne
    {
        return $this->hasOne(StockStats::class);
    }

    public function intervals(): HasOne
    {
        return $this->hasOne(StockIntervals::class);
    }

    public function salesIntervals(): HasOne
    {
        return $this->hasOne(StockSalesInterval::class);
    }

    public function stockFamily(): BelongsTo
    {
        return $this->belongsTo(StockFamily::class);
    }

    public function images(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'media_stock')->withTimestamps()
            ->withPivot(['public', 'owner_type', 'owner_id'])
            ->wherePivot('type', 'image');
    }

    public function barcode(): MorphToMany
    {
        return $this->morphToMany(Barcode::class, 'mode', 'model_has_barcodes')->withTimestamps();
    }

    public function supplierProducts(): BelongsToMany
    {
        return $this->belongsToMany(SupplierProduct::class, 'stock_has_supplier_products')
            ->withPivot(['priority', 'status', 'source_id', 'source_slug', 'fetched_at', 'last_fetched_at'])->withTimestamps();
    }

    public function getMainSupplierProduct(): SupplierProduct
    {
        return$this->supplierProducts()->where('available', true)->orderBy('priority', 'desc')->first();
    }

    public function timeSeries(): HasMany
    {
        return $this->hasMany(StockTimeSeries::class);
    }

}
