<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 23 Jan 2024 15:20:09 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Inventory;

use App\Enums\Inventory\OrgStockFamily\OrgStockFamilyStateEnum;
use App\Models\Goods\StockFamily;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\Traits\HasUniversalSearch;
use App\Models\Traits\InOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * App\Models\Inventory\OrgStockFamily
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int|null $stock_family_id
 * @property string $slug
 * @property string $code
 * @property string|null $name
 * @property OrgStockFamilyStateEnum $state
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $activated_in_organisation_at
 * @property string|null $discontinuing_in_organisation_at
 * @property string|null $discontinued_in_organisation_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $source_id
 * @property-read Group $group
 * @property-read \App\Models\Inventory\OrgStockFamilyIntervals|null $intervals
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Inventory\OrgStock> $orgStocks
 * @property-read Organisation $organisation
 * @property-read \App\Models\Inventory\OrgStockFamilySalesIntervals|null $salesIntervals
 * @property-read \App\Models\Inventory\OrgStockFamilyStats|null $stats
 * @property-read StockFamily|null $stockFamily
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Inventory\OrgStockFamilyTimeSeries> $timeSeries
 * @property-read \App\Models\Helpers\UniversalSearch|null $universalSearch
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgStockFamily newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgStockFamily newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgStockFamily onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgStockFamily query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgStockFamily withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgStockFamily withoutTrashed()
 * @mixin \Eloquent
 */
class OrgStockFamily extends Model
{
    use HasSlug;
    use SoftDeletes;
    use HasUniversalSearch;
    use InOrganisation;

    protected $casts = [
        'data'  => 'array',
        'state' => OrgStockFamilyStateEnum::class

    ];

    protected $attributes = [
        'data' => '{}',
    ];

    protected $guarded = [];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(function () {
                return $this->code. ' '.$this->organisation->code;
            })
            ->slugsShouldBeNoLongerThan(128)
            ->doNotGenerateSlugsOnUpdate()
            ->saveSlugsTo('slug');
    }

    public function orgStocks(): HasMany
    {
        return $this->hasMany(OrgStock::class);
    }

    public function stats(): HasOne
    {
        return $this->hasOne(OrgStockFamilyStats::class);
    }

    public function intervals(): HasOne
    {
        return $this->hasOne(OrgStockFamilyIntervals::class);
    }

    public function salesIntervals(): HasOne
    {
        return $this->hasOne(OrgStockFamilySalesIntervals::class);
    }

    public function stockFamily(): BelongsTo
    {
        return $this->belongsTo(StockFamily::class);
    }

    public function timeSeries(): HasMany
    {
        return $this->hasMany(OrgStockFamilyTimeSeries::class);
    }

}
