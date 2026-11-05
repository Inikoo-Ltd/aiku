<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-04 10:00
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Procurement\AgentWarehouseStock;

use App\Actions\OrgAction;
use App\Enums\Procurement\AgentWarehouseStock\AgentWarehouseStockStateEnum;
use App\Models\Procurement\AgentWarehouseStock;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use App\Actions\Procurement\PurchaseOrder\RecordPurchaseOrderHandedOverWhenReady;

/**
 * The agent packs one line of its warehouse for the next container, whole or only some of its units. Once packed, the
 * goods receipt line it came from can no longer be checked or cancelled, so goods packed before they were checked stay
 * unchecked.
 */
class PackAgentWarehouseStock extends OrgAction
{
    private const float QUANTITY_TOLERANCE = 0.0001;

    public function handle(AgentWarehouseStock $agentWarehouseStock, ?float $quantity = null): AgentWarehouseStock
    {
        $packed = DB::transaction(function () use ($agentWarehouseStock, $quantity) {
            $stock = AgentWarehouseStock::whereKey($agentWarehouseStock->id)->lockForUpdate()->firstOrFail();

            return self::packLocked($stock, $quantity);
        });

        RecordPurchaseOrderHandedOverWhenReady::make()->forPurchaseOrderIds([$packed->purchase_order_id]);

        return $packed;
    }

    /**
     * Packs a stock row already locked for update. Packing part of it leaves the rest as a received row of its own. A row
     * from a goods receipt is packed whole: checking its receipt line sets the row's quantity, which a split would undo.
     */
    public static function packLocked(AgentWarehouseStock $stock, ?float $quantity = null): AgentWarehouseStock
    {
        $available = (float) $stock->quantity;
        $quantity  = $quantity === null ? $available : round($quantity, 3);
        $code      = $stock->supplierProduct?->code;

        if ($stock->state !== AgentWarehouseStockStateEnum::RECEIVED || $available <= 0) {
            throw ValidationException::withMessages(['pack' => __(':code is not waiting to be packed', ['code' => $code])]);
        }

        if ($quantity <= 0 || $quantity > $available + self::QUANTITY_TOLERANCE) {
            throw ValidationException::withMessages(['pack' => __(':code: pack between 1 and :units units', ['code' => $code, 'units' => $available])]);
        }

        if ($available - $quantity <= self::QUANTITY_TOLERANCE) {
            $stock->update(['state' => AgentWarehouseStockStateEnum::PACKED_FOR_NEXT_CONTAINER]);

            return $stock;
        }

        if ($stock->agent_goods_receipt_id) {
            throw ValidationException::withMessages(['pack' => __(':code comes from a goods receipt and is packed whole', ['code' => $code])]);
        }

        $stock->update(['quantity' => round($available - $quantity, 3)]);

        $packed = $stock->replicate();
        $packed->fill([
            'quantity' => $quantity,
            'state'    => AgentWarehouseStockStateEnum::PACKED_FOR_NEXT_CONTAINER,
        ])->save();

        return $packed;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("procurement.{$this->organisation->id}.edit");
    }

    public function rules(): array
    {
        return [
            'quantity' => ['sometimes', 'nullable', 'numeric', 'gt:0'],
        ];
    }

    public function asController(Organisation $organisation, AgentWarehouseStock $agentWarehouseStock, ActionRequest $request): AgentWarehouseStock
    {
        abort_unless($organisation->agent && $agentWarehouseStock->agent_id === $organisation->agent->id, 404);
        $this->initialisation($organisation, $request);

        return $this->handle($agentWarehouseStock, isset($this->validatedData['quantity']) ? (float) $this->validatedData['quantity'] : null);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
