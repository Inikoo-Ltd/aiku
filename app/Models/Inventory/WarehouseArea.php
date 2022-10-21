<?php
/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Tue, 30 Aug 2022 12:13:40 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia F
 */

namespace App\Models\Inventory;

use App\Actions\Inventory\Warehouse\HydrateWarehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder;

/**
 * App\Models\Inventory\WarehouseArea
 *
 * @property int $id
 * @property int $warehouse_id
 * @property string $code
 * @property string $name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property int|null $source_id
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Inventory\Location[] $locations
 * @property-read int|null $locations_count
 * @property-read \App\Models\Inventory\WarehouseAreaStats|null $stats
 * @property-read \App\Models\Inventory\Warehouse $warehouse
 * @method static \Illuminate\Database\Eloquent\Builder|WarehouseArea newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|WarehouseArea newQuery()
 * @method static Builder|WarehouseArea onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|WarehouseArea query()
 * @method static \Illuminate\Database\Eloquent\Builder|WarehouseArea whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WarehouseArea whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WarehouseArea whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WarehouseArea whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WarehouseArea whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WarehouseArea whereSourceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WarehouseArea whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WarehouseArea whereWarehouseId($value)
 * @method static Builder|WarehouseArea withTrashed()
 * @method static Builder|WarehouseArea withoutTrashed()
 * @mixin \Eloquent
 */
class WarehouseArea extends Model
{
    use SoftDeletes;
    protected $guarded = [];

    protected static function booted()
    {
        static::created(
            function (WarehouseArea $warehouseArea) {
                HydrateWarehouse::make()->warehouseAreas($warehouseArea->warehouse);
            }
        );
        static::deleted(
            function (WarehouseArea $warehouseArea) {
                HydrateWarehouse::make()->warehouseAreas($warehouseArea->warehouse);

            }
        );

        static::updated(function (WarehouseArea $warehouseArea) {
            if (!$warehouseArea->wasRecentlyCreated) {
                if ($warehouseArea->wasChanged('warehouse_id')) {
                    HydrateWarehouse::make()->warehouseAreas($warehouseArea->warehouse);
                }
            }
        });
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function stats(): HasOne
    {
        return $this->hasOne(WarehouseAreaStats::class);
    }

}
