<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 17 Apr 2023 13:56:26 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\Procurement;

use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Models\Inventory\OrgStock;
use App\Models\SupplyChain\HistoricSupplierProduct;
use App\Models\SupplyChain\SupplierProduct;
use App\Models\Traits\InOrganisation;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\Procurement\PurchaseOrderTransaction
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $purchase_order_id
 * @property int|null $supplier_product_id
 * @property int|null $historic_supplier_product_id
 * @property int|null $org_supplier_product_id
 * @property int|null $stock_id Null allowed when org_stock is exclusive to an organization
 * @property int $org_stock_id
 * @property PurchaseOrderTransactionStateEnum $state
 * @property PurchaseOrderTransactionDeliveryStateEnum $delivery_state
 * @property string|null $quantity_ordered
 * @property string|null $quantity_dispatched
 * @property string|null $quantity_fail
 * @property string|null $quantity_cancelled
 * @property string $net_amount
 * @property string|null $grp_net_amount
 * @property string|null $org_net_amount
 * @property string|null $grp_exchange
 * @property string|null $org_exchange
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $fetched_at
 * @property string|null $last_fetched_at
 * @property string|null $deleted_at
 * @property string|null $source_id
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read HistoricSupplierProduct|null $historicSupplierProduct
 * @property-read OrgStock $orgStock
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \App\Models\Procurement\PurchaseOrder $purchaseOrder
 * @property-read SupplierProduct|null $supplierProduct
 * @method static \Database\Factories\Procurement\PurchaseOrderTransactionFactory factory($count = null, $state = [])
 * @method static Builder<static>|PurchaseOrderTransaction newModelQuery()
 * @method static Builder<static>|PurchaseOrderTransaction newQuery()
 * @method static Builder<static>|PurchaseOrderTransaction query()
 * @mixin Eloquent
 */
class PurchaseOrderTransaction extends Model
{
    use HasFactory;
    use InOrganisation;

    protected $casts = [
        'data'            => 'array',
        'state'           => PurchaseOrderTransactionStateEnum::class,
        'delivery_state' => PurchaseOrderTransactionDeliveryStateEnum::class
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    protected $guarded = [];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function supplierProduct(): BelongsTo
    {
        return $this->belongsTo(SupplierProduct::class);
    }

    public function historicSupplierProduct(): BelongsTo
    {
        return $this->belongsTo(HistoricSupplierProduct::class);
    }

    public function orgStock(): BelongsTo
    {
        return $this->belongsTo(OrgStock::class);
    }
}
