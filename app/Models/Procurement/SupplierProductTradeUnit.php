<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 09 May 2023 16:09:05 Malaysia Time, Pantai Lembeng, Bali, Id
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\Procurement;

use App\Models\SupplyChain\Supplier;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * App\Models\Procurement\SupplierProductTradeUnit
 *
 * @property int $id
 * @property int|null $supplier_product_id
 * @property int|null $trade_unit_id
 * @property float $package_quantity
 * @property float|null $carton_quantity
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Supplier $supplier
 * @method static Builder|SupplierProductTradeUnit newModelQuery()
 * @method static Builder|SupplierProductTradeUnit newQuery()
 * @method static Builder|SupplierProductTradeUnit query()
 * @mixin Eloquent
 */
class SupplierProductTradeUnit extends Pivot
{
    protected $table     = 'supplier_product_trade_unit';
    public $incrementing = true;

    protected $guarded = [];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
