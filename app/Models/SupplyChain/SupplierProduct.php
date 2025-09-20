<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 18 Jan 2024 19:33:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\SupplyChain;

use App\Enums\SupplyChain\SupplierProduct\SupplierProductStateEnum;
use App\Enums\SupplyChain\SupplierProduct\SupplierProductTradeUnitCompositionEnum;
use App\Models\Goods\Stock;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\UniversalSearch;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\SysAdmin\Group;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasUniversalSearch;
use App\Models\Traits\InGroup;
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
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * App\Models\SupplyChain\SupplierProduct
 *
 * @property int $id
 * @property int $group_id
 * @property string $slug
 * @property string $code
 * @property string|null $name
 * @property string|null $description
 * @property SupplierProductTradeUnitCompositionEnum|null $trade_unit_composition
 * @property int|null $current_historic_supplier_product_id
 * @property int|null $image_id
 * @property int|null $supplier_id
 * @property int|null $agent_id
 * @property SupplierProductStateEnum $state
 * @property bool $is_available
 * @property numeric $cost unit cost
 * @property int $currency_id
 * @property int|null $units_per_pack units per pack
 * @property int|null $units_per_carton units per carton
 * @property string|null $cbm carton cubic meters
 * @property array<array-key, mixed> $settings
 * @property array<array-key, mixed> $data
 * @property string|null $activated_at
 * @property string|null $discontinuing_at
 * @property string|null $discontinued_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $fetched_at
 * @property \Illuminate\Support\Carbon|null $last_fetched_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $source_slug
 * @property string|null $source_id
 * @property array<array-key, mixed> $sources
 * @property-read \App\Models\SupplyChain\Agent|null $agent
 * @property-read Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read Group $group
 * @property-read \App\Models\SupplyChain\HistoricSupplierProduct|null $historicSupplierProduct
 * @property-read Collection<int, \App\Models\SupplyChain\HistoricSupplierProduct> $historicSupplierProducts
 * @property-read Collection<int, OrgSupplierProduct> $orgSupplierProducts
 * @property-read \App\Models\SupplyChain\SupplierProductStats|null $stats
 * @property-read Collection<int, Stock> $stocks
 * @property-read \App\Models\SupplyChain\Supplier|null $supplier
 * @property-read Collection<int, TradeUnit> $tradeUnits
 * @property-read UniversalSearch|null $universalSearch
 * @method static \Database\Factories\SupplyChain\SupplierProductFactory factory($count = null, $state = [])
 * @method static Builder<static>|SupplierProduct newModelQuery()
 * @method static Builder<static>|SupplierProduct newQuery()
 * @method static Builder<static>|SupplierProduct onlyTrashed()
 * @method static Builder<static>|SupplierProduct query()
 * @method static Builder<static>|SupplierProduct withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|SupplierProduct withoutTrashed()
 * @mixin Eloquent
 */
class SupplierProduct extends Model implements Auditable
{
    use SoftDeletes;
    use HasSlug;
    use HasUniversalSearch;
    use HasFactory;
    use HasHistory;
    use InGroup;

    protected $casts = [
        'cost'                   => 'decimal:4',
        'data'                   => 'array',
        'settings'               => 'array',
        'sources'                => 'array',
        'status'                 => 'boolean',
        'state'                  => SupplierProductStateEnum::class,
        'trade_unit_composition' => SupplierProductTradeUnitCompositionEnum::class,
        'fetched_at'             => 'datetime',
        'last_fetched_at'        => 'datetime',
    ];

    protected $attributes = [
        'data'     => '{}',
        'settings' => '{}',
        'sources'  => '{}',
    ];

    protected $guarded = [];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('code')
            ->doNotGenerateSlugsOnUpdate()
            ->saveSlugsTo('slug');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function generateTags(): array
    {
        return [
            'supply-chain'
        ];
    }

    protected array $auditInclude = [
        'code',
        'name',
        'status',
        'description',
        'cost',
        'currency_id',
        'units_per_pack',
        'units_per_carton',
    ];

    public function historicSupplierProducts(): HasMany
    {
        return $this->hasMany(HistoricSupplierProduct::class);
    }

    public function historicSupplierProduct(): BelongsTo
    {
        return $this->belongsTo(HistoricSupplierProduct::class, 'current_historic_supplier_product_id');
    }

    public function stats(): HasOne
    {
        return $this->hasOne(SupplierProductStats::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
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

    public function orgSupplierProducts(): HasMany
    {
        return $this->hasMany(OrgSupplierProduct::class);
    }

    public function stocks(): BelongsToMany
    {
        return $this->belongsToMany(Stock::class, 'stock_has_supplier_products');
    }

    //    public function getMainStock(): Stock
    //    {
    //        return$this->stocks()->where('available', true)->orderBy('created_at')->first();
    //    }
}
