<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 18 Jan 2024 16:37:54 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Procurement;

use App\Models\SupplyChain\Agent;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\Traits\HasUniversalSearch;
use App\Models\Traits\InOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * App\Models\Procurement\OrgAgent
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property string $slug
 * @property int $agent_id
 * @property bool $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $source_id
 * @property-read Agent $agent
 * @property-read Group $group
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Procurement\OrgSupplierProduct> $orgSupplierProducts
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Procurement\OrgSupplier> $orgSuppliers
 * @property-read Organisation $organisation
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Procurement\PurchaseOrder> $purchaseOrders
 * @property-read \App\Models\Procurement\OrgAgentStats|null $stats
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Procurement\StockDelivery> $stockDeliveries
 * @property-read \App\Models\Helpers\UniversalSearch|null $universalSearch
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgAgent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgAgent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgAgent query()
 *
 * @mixin \Eloquent
 */
class OrgAgent extends Model
{
    use HasSlug;
    use HasUniversalSearch;
    use InOrganisation;

    protected $guarded = [];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(function () {
                return $this->agent->code.'-'.$this->organisation->code;
            })
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->slugsShouldBeNoLongerThan(128);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function stats(): HasOne
    {
        return $this->hasOne(OrgAgentStats::class);
    }

    public function orgSuppliers(): HasMany
    {
        return $this->hasMany(OrgSupplier::class);
    }

    public function purchaseOrders(): MorphMany
    {
        return $this->morphMany(PurchaseOrder::class, 'parent');
    }

    public function stockDeliveries(): MorphMany
    {
        return $this->morphMany(StockDelivery::class, 'parent');
    }

    public function orgSupplierProducts(): HasMany
    {
        return $this->hasMany(OrgSupplierProduct::class);
    }
}
