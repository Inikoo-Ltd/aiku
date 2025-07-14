<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Tue, 30 Aug 2022 12:11:40 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia F
 */

namespace App\Models\Inventory;

use App\Enums\Inventory\Warehouse\WarehouseStateEnum;
use App\Models\Analytics\AikuSection;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\PickingRoute;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Helpers\Address;
use App\Models\Helpers\UniversalSearch;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\Role;
use App\Models\Traits\HasAddress;
use App\Models\Traits\HasAddresses;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasUniversalSearch;
use App\Models\Traits\InOrganisation;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * App\Models\Inventory\Warehouse
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property string $slug
 * @property string $code
 * @property string $name
 * @property WarehouseStateEnum $state
 * @property int|null $address_id
 * @property array<array-key, mixed> $location
 * @property array<array-key, mixed> $settings
 * @property array<array-key, mixed> $data
 * @property bool $allow_stocks
 * @property bool $allow_fulfilment
 * @property bool $allow_dropshipping
 * @property \Illuminate\Support\Carbon|null $fetched_at
 * @property \Illuminate\Support\Carbon|null $last_fetched_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $source_id
 * @property-read Address|null $address
 * @property-read Collection<int, Address> $addresses
 * @property-read Collection<int, AikuSection> $aikuScopedSections
 * @property-read Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read Collection<int, DeliveryNote> $deliveryNotes
 * @property-read Collection<int, Fulfilment> $fulfilments
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read Collection<int, \App\Models\Inventory\Location> $locations
 * @property-read Organisation $organisation
 * @property-read Collection<int, PalletDelivery> $palletDeliveries
 * @property-read Collection<int, PalletReturn> $palletReturns
 * @property-read Collection<int, Pallet> $pallets
 * @property-read Collection<int, PickingRoute> $pickingRoutes
 * @property-read Collection<int, Role> $roles
 * @property-read \App\Models\Inventory\WarehouseStats|null $stats
 * @property-read Collection<int, \App\Models\Inventory\WarehouseTimeSeries> $timeSeries
 * @property-read UniversalSearch|null $universalSearch
 * @property-read Collection<int, UniversalSearch> $universalSearches
 * @property-read Collection<int, \App\Models\Inventory\WarehouseArea> $warehouseAreas
 * @method static \Database\Factories\Inventory\WarehouseFactory factory($count = null, $state = [])
 * @method static Builder<static>|Warehouse newModelQuery()
 * @method static Builder<static>|Warehouse newQuery()
 * @method static Builder<static>|Warehouse onlyTrashed()
 * @method static Builder<static>|Warehouse query()
 * @method static Builder<static>|Warehouse withTrashed()
 * @method static Builder<static>|Warehouse withoutTrashed()
 * @mixin Eloquent
 */
class Warehouse extends Model implements Auditable
{
    use HasSlug;
    use SoftDeletes;
    use HasUniversalSearch;
    use HasFactory;
    use HasHistory;
    use InOrganisation;
    use HasAddress;
    use HasAddresses;

    protected $casts = [
        'state'              => WarehouseStateEnum::class,
        'data'               => 'array',
        'settings'           => 'array',
        'location'           => 'array',
        'fetched_at'         => 'datetime',
        'last_fetched_at'    => 'datetime',
    ];

    protected $attributes = [
        'data'     => '{}',
        'settings' => '{}',
        'location' => '{}'
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
        'name',
        'state',
        'allow_stocks',
        'allow_fulfilment',
        'allow_dropshipping'
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('code')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->slugsShouldBeNoLongerThan(64);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    public function warehouseAreas(): HasMany
    {
        return $this->hasMany(WarehouseArea::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function stats(): HasOne
    {
        return $this->hasOne(WarehouseStats::class);
    }

    public function roles(): MorphMany
    {
        return $this->morphMany(Role::class, 'scope');
    }

    public function fulfilments(): BelongsToMany
    {
        return $this->belongsToMany(Fulfilment::class);
    }

    public function pallets(): HasMany
    {
        return $this->hasMany(Pallet::class);
    }

    public function palletDeliveries(): HasMany
    {
        return $this->hasMany(PalletDelivery::class);
    }

    public function palletReturns(): HasMany
    {
        return $this->hasMany(PalletReturn::class);
    }

    public function deliveryNotes(): HasMany
    {
        return $this->hasMany(DeliveryNote::class);
    }

    public function universalSearches(): HasMany
    {
        return $this->hasMany(UniversalSearch::class);
    }

    public function pickingRoutes(): HasMany
    {
        return $this->hasMany(PickingRoute::class);
    }

    public function aikuScopedSections(): MorphToMany
    {
        return $this->morphToMany(AikuSection::class, 'model', 'aiku_scoped_sections');
    }

    public function timeSeries(): HasMany
    {
        return $this->hasMany(WarehouseTimeSeries::class);
    }

    public function pickingSessions(): HasMany
    {
        return $this->hasMany(PickingSession::class);
    }

}
