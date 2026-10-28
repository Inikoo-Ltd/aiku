<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 18 Jan 2024 19:33:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\SupplyChain;

use App\Enums\Goods\TradeUnit\TradeUnitPackagingMaterialEnum;
use App\Enums\SupplyChain\Supplier\SupplierPackingMethodEnum;
use App\Enums\SupplyChain\SupplierProduct\SupplierProductAvailabilityStatusEnum;
use App\Enums\SupplyChain\SupplierProduct\SupplierProductStateEnum;
use App\Enums\SupplyChain\SupplierProduct\SupplierProductUnavailableReasonEnum;
use App\Enums\SupplyChain\SupplierProduct\SupplierProductTradeUnitCompositionEnum;
use App\Enums\SupplyChain\SupplierProduct\SupplierUnitEnum;
use App\Models\Goods\Stock;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Currency;
use App\Models\Inventory\OrgStock;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\SysAdmin\Group;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasImage;
use App\Models\Traits\HasSearch;
use App\Models\Traits\InGroup;
use Eloquent;
use Illuminate\Support\Carbon;
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
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
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
 * @property numeric|null $cbm carton cubic meters
 * @property int|null $carton_weight grams
 * @property int|null $carton_net_weight grams
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
 * @property numeric $extra_costs Estimated percentage of extra costs
 * @property int|null $minimum_carton_order MOQ in cartons
 * @property array{l: float, w: float, h: float}|null $carton_dimensions cm
 * @property Carbon|null $lead_time_confirmed_at
 * @property int|null $lead_time_confirmed_by_user_id
 * @property SupplierProductAvailabilityStatusEnum $availability_status
 * @property SupplierProductUnavailableReasonEnum|null $unavailable_reason
 * @property Carbon|null $available_from
 * @property numeric|null $availability_new_cost new unit cost the supplier asks
 * @property string|null $availability_note
 * @property array<string, mixed> $packaging_terms
 * @property list<string>|null $packing_instructions the steps the packers follow, in order
 * @property-read \App\Models\SupplyChain\Agent|null $agent
 * @property-read Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read Currency $currency
 * @property-read Group|null $group
 * @property-read \App\Models\Helpers\Media|null $image
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $images
 * @property-read \App\Models\SupplyChain\HistoricSupplierProduct|null $historicSupplierProduct
 * @property-read Collection<int, \App\Models\SupplyChain\HistoricSupplierProduct> $historicSupplierProducts
 * @property-read Collection<int, OrgSupplierProduct> $orgSupplierProducts
 * @property-read \App\Models\SupplyChain\SupplierProductStats|null $stats
 * @property-read Collection<int, Stock> $stocks
 * @property-read \App\Models\SupplyChain\Supplier|null $supplier
 * @property-read Collection<int, TradeUnit> $tradeUnits
 * @property-read Collection<int, SupplierProductPackingSpec> $packingSpecs
 * @property-read SupplierProductPackingSpec|null $livePackingSpec
 * @method static \Database\Factories\SupplyChain\SupplierProductFactory factory($count = null, $state = [])
 * @method static Builder<static>|SupplierProduct newModelQuery()
 * @method static Builder<static>|SupplierProduct newQuery()
 * @method static Builder<static>|SupplierProduct onlyTrashed()
 * @method static Builder<static>|SupplierProduct query()
 * @method static Builder<static>|SupplierProduct withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|SupplierProduct withoutTrashed()
 * @mixin Eloquent
 */
class SupplierProduct extends Model implements HasMedia, Auditable
{
    use SoftDeletes;
    use HasImage;
    use HasSlug;
    use HasFactory;
    use HasHistory;
    use InGroup;
    use HasSearch;

    protected $casts = [
        'cost'                   => 'decimal:4',
        'extra_costs'            => 'decimal:3',
        'data'                   => 'array',
        'settings'               => 'array',
        'sources'                => 'array',
        'status'                 => 'boolean',
        'state'                  => SupplierProductStateEnum::class,
        'packing_method'         => SupplierPackingMethodEnum::class,
        'packing_material'       => TradeUnitPackagingMaterialEnum::class,
        'trade_unit_composition' => SupplierProductTradeUnitCompositionEnum::class,
        'supplier_unit'          => SupplierUnitEnum::class,
        'units_per_supplier_unit' => 'decimal:4',
        'carton_dimensions'      => 'array',
        'packaging_terms'        => 'array',
        'packing_instructions'   => 'array',
        'availability_status'    => SupplierProductAvailabilityStatusEnum::class,
        'unavailable_reason'     => SupplierProductUnavailableReasonEnum::class,
        'available_from'         => 'date',
        'availability_new_cost'  => 'decimal:4',
        'lead_time_confirmed_at' => 'datetime',
        'fetched_at'             => 'datetime',
        'last_fetched_at'        => 'datetime',
    ];

    protected $attributes = [
        'data'     => '{}',
        'settings' => '{}',
        'sources'  => '{}',
        'packaging_terms' => '{}',
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
        'supplier_id',
        'code',
        'name',
        'status',
        'description',
        'cost',
        'currency_id',
        'units_per_pack',
        'units_per_carton',
        'supplier_unit',
        'units_per_supplier_unit',
        'cbm',
        'carton_dimensions',
        'carton_weight',
        'carton_net_weight',
        'minimum_carton_order',
        'estimated_lead_time_days',
        'lead_time_confirmed_at',
        'is_available',
        'availability_status',
        'unavailable_reason',
        'available_from',
        'availability_new_cost',
        'availability_note',
    ];

    public function searchIndexShouldBeUpdated(): bool
    {
        return $this->wasRecentlyCreated || $this->wasChanged(['code', 'name', 'state']);
    }

    public function toSearchableArray(): array
    {
        return [
            'id'               => (string)$this->id,
            'code'             => $this->code,
            'name'             => (string)$this->name,
            'slug'             => $this->slug,
            'state'            => $this->state?->value,
            'created_at'       => is_string($this->created_at) ? Carbon::parse($this->created_at)->timestamp : $this->created_at->timestamp,
            'organisation_ids' => $this->orgSupplierProducts()->pluck('organisation_id')->all(),
            'agent_id'         => $this->agent_id,
            'supplier_id'      => $this->supplier_id,
        ];
    }

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

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function unitsPerSupplierUnit(): float
    {
        return $this->supplier_unit && (float) $this->units_per_supplier_unit > 0 ? (float) $this->units_per_supplier_unit : 1.0;
    }

    /**
     * How many of our units one supplier unit should hold, judged by the weight of the single trade unit
     * we count in; null when the supplier unit is not a weight or the weight is unknown.
     */
    public function unitsPerSupplierUnitByWeight(?SupplierUnitEnum $supplierUnit = null): ?float
    {
        $grams = ($supplierUnit ?? $this->supplier_unit)?->grams();
        if (!$grams || $this->tradeUnits->count() !== 1) {
            return null;
        }

        $tradeUnit = $this->tradeUnits->first();
        $weight    = (float) ($tradeUnit->net_weight ?: $tradeUnit->gross_weight) * (float) $tradeUnit->pivot->quantity;

        return $weight > 0 ? round($grams / $weight, 4) : null;
    }

    /**
     * A warning when the supplier unit says one thing and the weight of what we count says another.
     */
    public function supplierUnitWarning(): ?string
    {
        $byWeight = $this->unitsPerSupplierUnitByWeight();
        if ($byWeight === null || !$this->supplier_unit) {
            return null;
        }

        if (abs($this->unitsPerSupplierUnit() - $byWeight) > 0.01 * $byWeight) {
            return __('One :unit should hold :expected units by the weight of the trade unit, but :factor is set.', [
                'unit'     => $this->supplier_unit->value,
                'expected' => (float) $byWeight,
                'factor'   => $this->unitsPerSupplierUnit(),
            ]);
        }

        return null;
    }


    /**
     * Units per pack as the SKO composition declares it: the packing every organisation holding
     * the one stock this supplier product feeds agrees on, null when there is no single answer.
     */
    public function skoPackedIn(): ?int
    {
        $stockIds = $this->stocks()->pluck('stocks.id');
        if ($stockIds->count() !== 1) {
            return null;
        }

        $packings = OrgStock::where('stock_id', $stockIds->first())->distinct()->pluck('packed_in');

        return $packings->count() === 1 && $packings->first() ? (int) $packings->first() : null;
    }

    public function packingSpecs(): HasMany
    {
        return $this->hasMany(SupplierProductPackingSpec::class)->orderByDesc('version');
    }

    /**
     * The packing spec in force: the latest version the agent declared that goods in has not replaced.
     */
    public function livePackingSpec(): HasOne
    {
        return $this->hasOne(SupplierProductPackingSpec::class)->ofMany(['version' => 'max'], fn (Builder $query) => $query->live());
    }
}
