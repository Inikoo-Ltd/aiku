<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 14 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Procurement;

use App\Enums\Procurement\AgentWarehouseStock\AgentWarehouseStockStateEnum;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\SupplierProduct;
use App\Models\SysAdmin\Organisation;
use App\Models\Traits\InGroup;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

/**
 * Goods an agent already holds in its own warehouse: received from the supplier, not yet in a container.
 *
 * @property int $id
 * @property int $group_id
 * @property int $agent_id
 * @property int $organisation_id
 * @property int $supplier_product_id
 * @property int|null $purchase_order_id
 * @property int|null $purchase_order_transaction_id
 * @property int|null $agent_goods_receipt_id
 * @property AgentWarehouseStockStateEnum $state
 * @property string $quantity
 * @property string|null $notes
 */
class AgentWarehouseStock extends Model
{
    use InGroup;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'state' => AgentWarehouseStockStateEnum::class,
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function supplierProduct(): BelongsTo
    {
        return $this->belongsTo(SupplierProduct::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function agentGoodsReceiptItem(): HasOne
    {
        return $this->hasOne(AgentGoodsReceiptItem::class);
    }

    public function agentGoodsReceipt(): BelongsTo
    {
        return $this->belongsTo(AgentGoodsReceipt::class);
    }

    public function purchaseOrderTransaction(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderTransaction::class);
    }

    /**
     * The units of a row the agent still holds: goods of its purchase order line put in a container (staged or
     * dispatched, not cancelled) have left the warehouse, taken from the line's packed rows first and then its oldest,
     * so a later goods receipt for the rest of a line stays held and a received row is not hidden by a packed one
     * that shipped (INI-206). Nothing writes the row when goods go in a container, so
     * every reader of what the agent holds goes through this, never through quantity alone.
     */
    public static function heldQuantitySql(string $table = 'agent_warehouse_stocks'): string
    {
        return "case when $table.purchase_order_transaction_id is null then $table.quantity else greatest(0, least($table.quantity,
            (select sum(earlier.quantity) from agent_warehouse_stocks as earlier where earlier.purchase_order_transaction_id = $table.purchase_order_transaction_id and earlier.quantity > 0 and (earlier.state = 'received', earlier.id) <= ($table.state = 'received', $table.id))
            - (select coalesce(sum(held_container_items.unit_quantity), 0) from stock_delivery_items as held_container_items
                join stock_deliveries as held_containers on held_containers.id = held_container_items.stock_delivery_id
                where (held_container_items.data->>'purchase_order_transaction_id')::bigint = $table.purchase_order_transaction_id
                and held_container_items.deleted_at is null and held_containers.deleted_at is null
                and held_container_items.state not in ('cancelled', 'not_received'))
        )) end";
    }

    public function scopeWhereHeld(Builder $query): Builder
    {
        return $query->where('agent_warehouse_stocks.quantity', '>', 0)->whereRaw(self::heldQuantitySql().' > 0');
    }

    public static function heldQuantityColumn(): Expression
    {
        return DB::raw(self::heldQuantitySql().' as quantity');
    }
}
