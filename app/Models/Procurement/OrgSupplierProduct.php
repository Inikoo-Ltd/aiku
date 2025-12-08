<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 09 May 2023 16:09:05 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\Procurement;

use App\Models\Inventory\OrgStock;
use App\Models\SupplyChain\SupplierProduct;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\Traits\InOrganisation;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * App\Models\Procurement\OrgSupplierProduct
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $supplier_product_id
 * @property int|null $org_agent_id
 * @property int|null $org_supplier_id
 * @property string $slug
 * @property string $state
 * @property bool $is_available
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $source_id
 * @property-read Group $group
 * @property-read \App\Models\Procurement\OrgAgent|null $orgAgent
 * @property-read OrgStock|null $orgStock
 * @property-read \App\Models\Procurement\OrgSupplier|null $orgSupplier
 * @property-read Organisation $organisation
 * @property-read \App\Models\Procurement\OrgSupplierProductStats|null $stats
 * @property-read SupplierProduct $supplierProduct
 *
 * @method static Builder<static>|OrgSupplierProduct newModelQuery()
 * @method static Builder<static>|OrgSupplierProduct newQuery()
 * @method static Builder<static>|OrgSupplierProduct query()
 *
 * @mixin Eloquent
 */
class OrgSupplierProduct extends Model
{
    use HasSlug;
    use InOrganisation;

    protected $table = 'org_supplier_products';

    protected $guarded = [];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(function () {
                return $this->supplierProduct->code.'-'.$this->organisation->code;
            })
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->slugsShouldBeNoLongerThan(128);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function stats(): HasOne
    {
        return $this->hasOne(OrgSupplierProductStats::class);
    }

    public function orgSupplier(): BelongsTo
    {
        return $this->belongsTo(OrgSupplier::class);
    }

    public function orgAgent(): BelongsTo
    {
        return $this->belongsTo(OrgAgent::class);
    }

    public function supplierProduct(): BelongsTo
    {
        return $this->belongsTo(SupplierProduct::class);
    }

    public function orgStock(): BelongsTo
    {
        return $this->belongsTo(OrgStock::class);
    }
}
