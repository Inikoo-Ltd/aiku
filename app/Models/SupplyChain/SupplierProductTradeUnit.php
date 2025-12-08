<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 06 Sept 2024 12:40:48 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\SupplyChain;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * App\Models\Procurement\SupplierProductTradeUnit
 *
 * @property-read \App\Models\SupplyChain\Supplier|null $supplier
 *
 * @method static Builder<static>|SupplierProductTradeUnit newModelQuery()
 * @method static Builder<static>|SupplierProductTradeUnit newQuery()
 * @method static Builder<static>|SupplierProductTradeUnit query()
 *
 * @mixin Eloquent
 */
class SupplierProductTradeUnit extends Pivot
{
    protected $table = 'supplier_product_trade_unit';

    public $incrementing = true;

    protected $guarded = [];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
