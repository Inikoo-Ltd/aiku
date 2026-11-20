<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-10-16 15:30
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Models\Procurement;

use App\Enums\Procurement\AgentGoodsReceipt\AgentGoodsReceiptStateEnum;
use App\Enums\Procurement\AgentWarehouseStock\AgentWarehouseStockStateEnum;
use App\Models\SupplyChain\SupplierProduct;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use App\Models\Traits\HasAttachments;
use App\Models\Traits\InGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;

/**
 * Units of one purchase order line a goods receipt brought, and the rejects found when they were checked. What passed
 * is held as the linked agent warehouse stock; rejected units are owed by the supplier again.
 *
 * @property int $id
 * @property int $group_id
 * @property int $agent_goods_receipt_id
 * @property int $organisation_id
 * @property int|null $purchase_order_id
 * @property int|null $purchase_order_transaction_id
 * @property int $supplier_product_id
 * @property int|null $agent_warehouse_stock_id
 * @property string $quantity
 * @property string|null $quantity_rejected
 * @property string|null $reject_reason
 * @property Carbon|null $checked_at
 * @property int|null $checked_by_user_id
 * @property-read AgentGoodsReceipt $agentGoodsReceipt
 * @property-read AgentWarehouseStock|null $agentWarehouseStock
 */
class AgentGoodsReceiptItem extends Model implements HasMedia
{
    use InGroup;
    use HasAttachments;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'checked_at' => 'datetime',
        ];
    }

    public function isChecked(): bool
    {
        return $this->checked_at !== null;
    }

    public function quantityAccepted(): float
    {
        return (float) $this->quantity - (float) $this->quantity_rejected;
    }

    /**
     * The units of this line already packed off its warehouse row (PackAgentWarehouseStock::packLocked).
     */
    public function quantitySplitOff(): float
    {
        if (!$this->agentWarehouseStock?->agent_goods_receipt_id) {
            return 0;
        }

        return (float) AgentWarehouseStock::splitOffsOf($this->agentWarehouseStock)->sum('quantity');
    }

    /**
     * Once its goods are packed for a container the line is history: its check can no longer be changed.
     */
    public function isEditable(): bool
    {
        return $this->agentGoodsReceipt->state === AgentGoodsReceiptStateEnum::RECEIVED
            && $this->agentWarehouseStock?->state === AgentWarehouseStockStateEnum::RECEIVED;
    }

    public function agentGoodsReceipt(): BelongsTo
    {
        return $this->belongsTo(AgentGoodsReceipt::class);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function purchaseOrderTransaction(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderTransaction::class);
    }

    public function supplierProduct(): BelongsTo
    {
        return $this->belongsTo(SupplierProduct::class);
    }

    public function agentWarehouseStock(): BelongsTo
    {
        return $this->belongsTo(AgentWarehouseStock::class);
    }

    public function checkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by_user_id');
    }
}
