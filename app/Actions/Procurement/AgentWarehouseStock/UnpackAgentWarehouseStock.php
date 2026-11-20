<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-10 10:00
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Procurement\AgentWarehouseStock;

use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrder\RecordPurchaseOrderHandedOverWhenReady;
use App\Enums\Procurement\AgentWarehouseStock\AgentWarehouseStockStateEnum;
use App\Models\Procurement\AgentWarehouseStock;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * The agent takes back a line marked packed by mistake: the row returns to received, waiting to be packed again. When
 * only part of a packed row went in a container, unpacking it shows the rest as received again, since what is held is
 * worked out from the containers of its purchase order line (AgentWarehouseStock::heldQuantitySql). A row whose units
 * all went in a container is left alone. A part packed off a goods receipt row is merged back into it.
 */
class UnpackAgentWarehouseStock extends OrgAction
{
    public string $commandSignature = 'agent_warehouse_stock:unpack {ids* : ids of the agent warehouse stock rows}';

    public function handle(AgentWarehouseStock $agentWarehouseStock): AgentWarehouseStock
    {
        $unpacked = $this->unpack($agentWarehouseStock);

        RecordPurchaseOrderHandedOverWhenReady::make()->forPurchaseOrderIds([$unpacked->purchase_order_id]);

        return $unpacked;
    }

    private function unpack(AgentWarehouseStock $agentWarehouseStock): AgentWarehouseStock
    {
        return DB::transaction(function () use ($agentWarehouseStock) {
            $stock = AgentWarehouseStock::whereKey($agentWarehouseStock->id)->lockForUpdate()->firstOrFail();

            return self::unpackLocked($stock);
        });
    }

    /**
     * Unpacks a stock row already locked for update. A part packed off a goods receipt row goes back into that row
     * once both are received, so checking its receipt line again counts it (AgentWarehouseStock::splitOffsOf).
     */
    public static function unpackLocked(AgentWarehouseStock $stock): AgentWarehouseStock
    {
        $code = $stock->supplierProduct?->code;

        if ($stock->state === AgentWarehouseStockStateEnum::RECEIVED) {
            throw ValidationException::withMessages(['unpack' => __(':code is not packed', ['code' => $code])]);
        }

        $heldQuantity = (float) AgentWarehouseStock::whereKey($stock->id)
            ->selectRaw(AgentWarehouseStock::heldQuantitySql().' as held_quantity')
            ->value('held_quantity');

        if ($heldQuantity <= 0) {
            throw ValidationException::withMessages(['unpack' => __(':code is already in a container', ['code' => $code])]);
        }

        $stock->update(['state' => AgentWarehouseStockStateEnum::RECEIVED]);

        return self::mergeIntoGoodsReceiptRow($stock);
    }

    private static function mergeIntoGoodsReceiptRow(AgentWarehouseStock $stock): AgentWarehouseStock
    {
        $receiptRowId = $stock->agent_goods_receipt_id ? DB::table('agent_goods_receipt_items')
            ->join('agent_warehouse_stocks', 'agent_warehouse_stocks.id', 'agent_goods_receipt_items.agent_warehouse_stock_id')
            ->where('agent_warehouse_stocks.agent_goods_receipt_id', $stock->agent_goods_receipt_id)
            ->where('agent_warehouse_stocks.purchase_order_transaction_id', $stock->purchase_order_transaction_id)
            ->where('agent_warehouse_stocks.supplier_product_id', $stock->supplier_product_id)
            ->value('agent_warehouse_stocks.id') : null;

        $receiptRow = $receiptRowId ? AgentWarehouseStock::whereKey($receiptRowId)->lockForUpdate()->first() : null;
        if (!$receiptRow || $receiptRow->state !== AgentWarehouseStockStateEnum::RECEIVED) {
            return $stock;
        }

        $receivedSplitOffs = AgentWarehouseStock::splitOffsOf($receiptRow)
            ->where('state', AgentWarehouseStockStateEnum::RECEIVED)
            ->lockForUpdate()
            ->get();

        if ($receivedSplitOffs->isEmpty()) {
            return $receiptRow;
        }

        $receiptRow->update(['quantity' => round((float) $receiptRow->quantity + (float) $receivedSplitOffs->sum('quantity'), 3)]);
        AgentWarehouseStock::whereIn('id', $receivedSplitOffs->pluck('id'))->delete();

        return $receiptRow;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("procurement.{$this->organisation->id}.edit");
    }

    public function asController(Organisation $organisation, AgentWarehouseStock $agentWarehouseStock, ActionRequest $request): AgentWarehouseStock
    {
        abort_unless($organisation->agent && $agentWarehouseStock->agent_id === $organisation->agent->id, 404);
        $this->initialisation($organisation, $request);

        return $this->handle($agentWarehouseStock);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }

    public function asCommand(Command $command): int
    {
        foreach ($command->argument('ids') as $id) {
            $stock = AgentWarehouseStock::with('supplierProduct:id,code')->findOrFail((int) $id);

            try {
                $this->handle($stock);
                $command->info("{$stock->id} {$stock->supplierProduct?->code}: unpacked");
            } catch (ValidationException $exception) {
                $command->error("{$stock->id}: ".collect($exception->errors())->flatten()->first());
            }
        }

        return 0;
    }
}
