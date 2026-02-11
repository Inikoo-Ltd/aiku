<?php

namespace App\Models\Inventory;

use App\Models\Traits\HasHistory;
use App\Models\Traits\HasUniversalSearch;
use App\Models\Traits\InWarehouse;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read \App\Models\SysAdmin\Group|null $group
 * @property-read \App\Models\SysAdmin\Organisation|null $organisation
 * @property-read \App\Models\Helpers\UniversalSearch|null $universalSearch
 * @property-read \App\Models\Inventory\Warehouse|null $warehouse
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PickingTrolley newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PickingTrolley newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PickingTrolley onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PickingTrolley query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PickingTrolley withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PickingTrolley withoutTrashed()
 * @mixin \Eloquent
 */
class PickingTrolley extends Model implements Auditable
{
    use SoftDeletes;
    use HasSlug;
    use HasUniversalSearch;
    use HasFactory;
    use HasHistory;
    use InWarehouse;

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
}
