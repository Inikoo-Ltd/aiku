<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 15 Nov 2024 19:52:45 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Inventory;

use App\Models\Procurement\OrgSupplierProduct;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 *
 *
 * @property int $id
 * @property int $stock_has_supplier_product_id
 * @property int $org_stock_id
 * @property int|null $org_supplier_product_id
 * @property bool $status
 * @property int $local_priority
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Inventory\OrgStock $orgStock
 * @property-read OrgSupplierProduct|null $orgSupplierProduct
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgStockHasOrgSupplierProduct newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgStockHasOrgSupplierProduct newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgStockHasOrgSupplierProduct query()
 * @mixin \Eloquent
 */
class OrgStockHasOrgSupplierProduct extends Model
{
    protected $table = 'org_stock_has_org_supplier_products';

    protected $casts = [
    ];

    protected $guarded = [];

    public function orgStock(): BelongsTo
    {
        return $this->belongsTo(OrgStock::class);
    }

    public function orgSupplierProduct(): BelongsTo
    {
        return $this->belongsTo(OrgSupplierProduct::class);
    }


}
