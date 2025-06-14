<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 23 Jan 2024 08:52:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Inventory;

use App\Enums\Inventory\OrgStock\OrgStockQuantityStatusEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Models\Goods\Stock;
use App\Models\Goods\TradeUnit;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\SysAdmin\Organisation;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasUniversalSearch;
use App\Models\Traits\InOrganisation;
use Illuminate\Database\Eloquent\Builder;
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
 * App\Models\Inventory\OrgStock
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int|null $stock_id
 * @property int|null $org_stock_family_id
 * @property int|null $customer_id
 * @property string $slug
 * @property string $code
 * @property string|null $name
 * @property string|null $unit_cost
 * @property string|null $unit_value
 * @property string $unit_commercial_value
 * @property bool $is_sellable_in_organisation
 * @property bool $is_raw_material_in_organisation
 * @property OrgStockStateEnum $state
 * @property OrgStockQuantityStatusEnum|null $quantity_status
 * @property string|null $quantity_in_locations stock quantity in units
 * @property string $value_in_locations
 * @property float|null $available_forecast days
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $activated_in_organisation_at
 * @property \Illuminate\Support\Carbon|null $discontinuing_in_organisation_at
 * @property \Illuminate\Support\Carbon|null $discontinued_in_organisation_at
 * @property \Illuminate\Support\Carbon|null $fetched_at
 * @property \Illuminate\Support\Carbon|null $last_fetched_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $source_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\Inventory\OrgStockIntervals|null $intervals
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Inventory\LocationOrgStock> $locationOrgStocks
 * @property-read \App\Models\Inventory\OrgStockFamily|null $orgStockFamily
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Inventory\OrgStockMovement> $orgStockMovements
 * @property-read \Illuminate\Database\Eloquent\Collection<int, OrgSupplierProduct> $orgSupplierProducts
 * @property-read Organisation $organisation
 * @property-read \App\Models\Inventory\OrgStockSalesInterval|null $salesIntervals
 * @property-read \App\Models\Inventory\OrgStockStats|null $stats
 * @property-read Stock|null $stock
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Inventory\OrgStockTimeSeries> $timeSeries
 * @property-read \Illuminate\Database\Eloquent\Collection<int, TradeUnit> $tradeUnits
 * @property-read \App\Models\Helpers\UniversalSearch|null $universalSearch
 * @method static \Database\Factories\Inventory\OrgStockFactory factory($count = null, $state = [])
 * @method static Builder<static>|OrgStock newModelQuery()
 * @method static Builder<static>|OrgStock newQuery()
 * @method static Builder<static>|OrgStock onlyTrashed()
 * @method static Builder<static>|OrgStock query()
 * @method static Builder<static>|OrgStock withTrashed()
 * @method static Builder<static>|OrgStock withoutTrashed()
 * @mixin \Eloquent
 */
class OrgStock extends Model implements Auditable
{
    use SoftDeletes;
    use HasSlug;
    use HasUniversalSearch;
    use HasFactory;
    use InOrganisation;
    use HasHistory;

    protected $casts = [
        'data'                             => 'array',
        'activated_in_organisation_at'     => 'datetime',
        'discontinuing_in_organisation_at' => 'datetime',
        'discontinued_in_organisation_at'  => 'datetime',
        'state'                            => OrgStockStateEnum::class,
        'quantity_status'                  => OrgStockQuantityStatusEnum::class,
        'fetched_at'                       => 'datetime',
        'last_fetched_at'                  => 'datetime',
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    protected $guarded = [];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

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
    ];


    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(function () {
                return $this->code.' '.$this->organisation->code;
            })
            ->doNotGenerateSlugsOnUpdate()
            ->saveSlugsTo('slug');
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    public function orgStockFamily(): BelongsTo
    {
        return $this->belongsTo(OrgStockFamily::class);
    }

    public function locationOrgStocks(): HasMany
    {
        return $this->hasMany(LocationOrgStock::class);
    }

    public function orgStockMovements(): HasMany
    {
        return $this->hasMany(OrgStockMovement::class);
    }

    public function stats(): HasOne
    {
        return $this->hasOne(OrgStockStats::class);
    }

    public function intervals(): HasOne
    {
        return $this->hasOne(OrgStockIntervals::class);
    }

    public function salesIntervals(): HasOne
    {
        return $this->hasOne(OrgStockSalesInterval::class);
    }

    public function orgSupplierProducts(): BelongsToMany
    {
        return $this->belongsToMany(OrgSupplierProduct::class, 'org_stock_has_org_supplier_products')
            ->withPivot(['status', 'local_priority'])->withTimestamps();
    }

    public function getMainOrgSupplierProduct(): OrgSupplierProduct
    {
        return $this->orgSupplierProducts()->where('status', true)->orderBy('local_priority', 'desc')->first();
    }

    public function tradeUnits(): MorphToMany
    {
        if ($this->stock_id) {
            return $this->stock->tradeUnits();
        }

        // Used in private stocks (stock_id=null)
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

    public function timeSeries(): HasMany
    {
        return $this->hasMany(OrgStockTimeSeries::class);
    }

    public function inventoryDailySnapshots(): HasMany
    {
        return $this->hasMany(InventoryDailySnapshot::class);
    }

}
