<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Tue, 30 Aug 2022 12:15:58 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia F
 */

namespace App\Models\Inventory;

use App\Enums\Inventory\Location\LocationStatusEnum;
use App\Models\Dispatching\PickingRoute;
use App\Models\Fulfilment\Pallet;
use App\Models\Helpers\UniversalSearch;
use App\Models\SysAdmin\Organisation;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasUniversalSearch;
use App\Models\Traits\InWarehouse;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * App\Models\Inventory\Location
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $warehouse_id
 * @property int|null $warehouse_area_id
 * @property string $slug
 * @property LocationStatusEnum $status
 * @property string $code
 * @property numeric $stock_value
 * @property string $stock_commercial_value
 * @property bool $is_empty
 * @property numeric|null $max_weight Max weight in Kg
 * @property numeric|null $max_volume Max volume in m3 (cbm)
 * @property bool $allow_stocks
 * @property bool $allow_dropshipping
 * @property bool $allow_fulfilment
 * @property bool $has_stock_slots
 * @property bool $has_dropshipping_slots
 * @property bool $has_fulfilment
 * @property string|null $barcode
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $audited_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $fetched_at
 * @property \Illuminate\Support\Carbon|null $last_fetched_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $source_id
 * @property-read Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read Collection<int, \App\Models\Inventory\LocationOrgStock> $locationOrgStocks
 * @property-read Collection<int, \App\Models\Inventory\LostAndFoundStock> $lostAndFoundStocks
 * @property-read Organisation $organisation
 * @property-read Collection<int, Pallet> $pallets
 * @property-read Collection<int, PickingRoute> $pickingRoutes
 * @property-read \App\Models\Inventory\LocationStats|null $stats
 * @property-read UniversalSearch|null $universalSearch
 * @property-read \App\Models\Inventory\Warehouse $warehouse
 * @property-read \App\Models\Inventory\WarehouseArea|null $warehouseArea
 * @method static \Database\Factories\Inventory\LocationFactory factory($count = null, $state = [])
 * @method static Builder<static>|Location newModelQuery()
 * @method static Builder<static>|Location newQuery()
 * @method static Builder<static>|Location onlyTrashed()
 * @method static Builder<static>|Location query()
 * @method static Builder<static>|Location withTrashed()
 * @method static Builder<static>|Location withoutTrashed()
 * @mixin Eloquent
 */
class Location extends Model implements Auditable
{
    use SoftDeletes;
    use HasSlug;
    use HasUniversalSearch;
    use HasFactory;
    use HasHistory;
    use InWarehouse;

    protected $casts = [
        'data'               => 'array',
        'audited_at'         => 'datetime',
        'status'             => LocationStatusEnum::class,
        'stock_value'        => 'decimal:2',
        'max_weight'         => 'decimal:3',
        'max_volume'         => 'decimal:4',
        'fetched_at'         => 'datetime',
        'last_fetched_at'    => 'datetime',
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    protected $guarded = [];

    public function generateTags(): array
    {
        return [
            'warehouse'
        ];
    }

    protected array $auditInclude = [
        'code',
        'status',
        'max_weight',
        'max_volume',
        'allow_stocks',
        'allow_dropshipping',
        'allow_fulfilment',
    ];

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

    public function warehouseArea(): BelongsTo
    {
        return $this->belongsTo(WarehouseArea::class);
    }

    public function locationOrgStocks(): HasMany
    {
        return $this->hasMany(LocationOrgStock::class);
    }

    public function stats(): HasOne
    {
        return $this->hasOne(LocationStats::class);
    }

    public function lostAndFoundStocks(): HasMany
    {
        return $this->hasMany(LostAndFoundStock::class);
    }

    public function pallets(): HasMany
    {
        return $this->hasMany(Pallet::class);
    }

    public function pickingRoutes(): BelongsToMany
    {
        return $this->belongsToMany(PickingRoute::class, 'picking_route_has_locations');
    }


}
