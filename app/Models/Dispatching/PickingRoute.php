<?php

/*
 * author Arya Permana - Kirin
 * created on 20-11-2024-10h-23m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Models\Dispatching;

use App\Models\Inventory\Location;
use App\Models\Traits\InWarehouse;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $warehouse_id
 * @property string $slug
 * @property string $name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $fetched_at
 * @property string|null $last_fetched_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $source_id
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Location> $locations
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \App\Models\Inventory\Warehouse $warehouse
 * @method static Builder<static>|PickingRoute newModelQuery()
 * @method static Builder<static>|PickingRoute newQuery()
 * @method static Builder<static>|PickingRoute onlyTrashed()
 * @method static Builder<static>|PickingRoute query()
 * @method static Builder<static>|PickingRoute withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|PickingRoute withoutTrashed()
 * @mixin Eloquent
 */
class PickingRoute extends Model
{
    use HasSlug;
    use SoftDeletes;
    use InWarehouse;

    protected $guarded = [];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->doNotGenerateSlugsOnUpdate()
            ->saveSlugsTo('slug');
    }

    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'picking_route_has_locations');
    }

}
