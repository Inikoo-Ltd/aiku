<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 09 May 2023 16:09:05 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\Procurement;

use App\Models\SupplyChain\Supplier;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\Traits\InOrganisation;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * App\Models\Procurement\OrgSupplier
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property string $slug
 * @property int $supplier_id
 * @property int|null $agent_id
 * @property int|null $org_agent_id
 * @property int|null $image_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $source_id
 * @property-read Group $group
 * @property-read Organisation $organisation
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Procurement\OrgSupplierProduct> $products
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Procurement\PurchaseOrder> $purchaseOrders
 * @property-read \App\Models\Procurement\OrgSupplierStats|null $stats
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Procurement\StockDelivery> $stockDeliveries
 * @property-read Supplier $supplier
 * @method static Builder|OrgSupplier newModelQuery()
 * @method static Builder|OrgSupplier newQuery()
 * @method static Builder|OrgSupplier query()
 * @mixin Eloquent
 */
class OrgSupplier extends Model
{
    use InOrganisation;
    use HasSlug;

    protected $table = 'org_suppliers';

    protected $guarded = [];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(function () {
                return $this->supplier->code.'-'.$this->organisation->code;
            })
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->slugsShouldBeNoLongerThan(64);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function stats(): HasOne
    {
        return $this->hasOne(OrgSupplierStats::class);
    }

    public function purchaseOrders(): MorphMany
    {
        return $this->morphMany(PurchaseOrder::class, 'parent');
    }

    public function stockDeliveries(): MorphMany
    {
        return $this->morphMany(StockDelivery::class, 'parent');
    }


    public function products(): HasMany
    {
        return $this->hasMany(OrgSupplierProduct::class);
    }

}
