<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Tue, 30 Aug 2022 12:13:40 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia F
 */

namespace App\Models\Inventory;

use App\Actions\Utils\Abbreviate;
use App\Models\SysAdmin\Organisation;
use App\Models\Traits\HasHistory;
use App\Models\Traits\InWarehouse;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Laravel\Scout\Searchable;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * App\Models\Inventory\WarehouseArea
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property string $slug
 * @property int $warehouse_id
 * @property string $code
 * @property string $name
 * @property numeric $unit_quantity
 * @property numeric $value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $fetched_at
 * @property Carbon|null $last_fetched_at
 * @property Carbon|null $deleted_at
 * @property string|null $source_id
 * @property float|null $picking_position
 * @property-read Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read \App\Models\SysAdmin\Group|null $group
 * @property-read Collection<int, \App\Models\Inventory\Location> $locations
 * @property-read Organisation $organisation
 * @property-read \App\Models\Inventory\WarehouseAreaStats|null $stats
 * @property-read Collection<int, \App\Models\Inventory\WarehouseAreaTimeSeries> $timeSeries
 * @property-read \App\Models\Inventory\Warehouse|null $warehouse
 * @method static \Database\Factories\Inventory\WarehouseAreaFactory factory($count = null, $state = [])
 * @method static Builder<static>|WarehouseArea newModelQuery()
 * @method static Builder<static>|WarehouseArea newQuery()
 * @method static Builder<static>|WarehouseArea onlyTrashed()
 * @method static Builder<static>|WarehouseArea query()
 * @method static Builder<static>|WarehouseArea withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|WarehouseArea withoutTrashed()
 * @mixin Eloquent
 */
class WarehouseArea extends Model implements Auditable
{
    use SoftDeletes;
    use HasSlug;
    use HasFactory;
    use HasHistory;
    use InWarehouse;
    use Searchable;

    protected $casts = [
        'unit_quantity'   => 'decimal:2',
        'value'           => 'decimal:2',
        'fetched_at'      => 'datetime',
        'last_fetched_at' => 'datetime',
    ];

    protected $guarded = [];

    public function searchIndexShouldBeUpdated(): bool
    {
        return $this->wasRecentlyCreated
            || $this->wasChanged([
                'code',
                'name',
                'warehouse_id'
            ]);
    }

    public function toSearchableArray(): array
    {
        return [
            'id'           => (string)$this->id,
            'warehouse_id' => $this->warehouse_id,
            'code'         => $this->code,
            'name'         => $this->name,
            'created_at'   => is_string($this->created_at) ? Carbon::parse($this->created_at)->timestamp : $this->created_at->timestamp,
        ];
    }

    public function generateTags(): array
    {
        return [
            'warehouse'
        ];
    }

    protected array $auditInclude = [
        'code',
        'name'
    ];


    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(function () {
                return Abbreviate::run($this->code, digits: true, maximumLength: 4);
            })
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->slugsShouldBeNoLongerThan(128);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function stats(): HasOne
    {
        return $this->hasOne(WarehouseAreaStats::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function timeSeries(): HasMany
    {
        return $this->hasMany(WarehouseAreaTimeSeries::class);
    }
}
