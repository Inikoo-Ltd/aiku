<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 06 Sept 2024 12:40:48 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\SupplyChain;

use App\Models\Traits\InGroup;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * App\Models\Procurement\HistoricSupplierProduct
 *
 * @property int $id
 * @property int $group_id
 * @property int|null $supplier_product_id
 * @property bool $status
 * @property string|null $code
 * @property int $units_per_pack
 * @property int $units_per_carton
 * @property string|null $cbm carton cubic meters
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $fetched_at
 * @property \Illuminate\Support\Carbon|null $last_fetched_at
 * @property string|null $source_id
 * @property array<array-key, mixed> $sources
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\SupplyChain\HistoricSupplierProductStats|null $stats
 * @property-read \App\Models\SupplyChain\SupplierProduct|null $supplierProduct
 *
 * @method static Builder<static>|HistoricSupplierProduct newModelQuery()
 * @method static Builder<static>|HistoricSupplierProduct newQuery()
 * @method static Builder<static>|HistoricSupplierProduct query()
 *
 * @mixin Eloquent
 */
class HistoricSupplierProduct extends Model
{
    use InGroup;

    protected $casts = [
        'status' => 'boolean',
        'sources' => 'array',
        'fetched_at' => 'datetime',
        'last_fetched_at' => 'datetime',
    ];

    protected $attributes = [
        'sources' => '{}',
    ];

    protected $guarded = [];

    public function supplierProduct(): BelongsTo
    {
        return $this->belongsTo(SupplierProduct::class);
    }

    public function stats(): HasOne
    {
        return $this->hasOne(HistoricSupplierProductStats::class);
    }
}
