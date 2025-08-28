<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Dec 2024 12:06:14 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Goods;

use App\Models\Inventory\OrgStockHasOrgSupplierProduct;
use App\Models\SupplyChain\SupplierProduct;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $stock_id
 * @property int|null $supplier_product_id
 * @property bool $available
 * @property int $priority
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, OrgStockHasOrgSupplierProduct> $orgStockHasOrgSupplierProducts
 * @property-read \App\Models\Goods\Stock $stock
 * @property-read SupplierProduct|null $supplierProduct
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockHasSupplierProduct newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockHasSupplierProduct newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockHasSupplierProduct query()
 * @mixin \Eloquent
 */
class StockHasSupplierProduct extends Model
{
    protected $table = 'stock_has_supplier_products';

    protected $casts = [
    ];

    protected $guarded = [];


    public function orgStockHasOrgSupplierProducts(): HasMany
    {
        return $this->hasMany(OrgStockHasOrgSupplierProduct::class);
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    public function supplierProduct(): BelongsTo
    {
        return $this->belongsTo(SupplierProduct::class);
    }


}
