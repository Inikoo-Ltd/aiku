<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Nov 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\GoodsIn\StockDelivery\UI\GetSupplierParcelPurchaseOrders;
use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrder\UpdatePurchaseOrderStateToConfirmed;
use App\Actions\Traits\Authorisations\WithGoodsInBookInAuthorisation;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Inventory\Warehouse;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * Goods in receives a parcel a direct supplier (no agent) sent, against the supplier's open orders: it enters the
 * SKOs that arrived per order line, and one stock delivery is made with only those lines and received at once.
 * Lines not in the parcel stay open on their orders. A submitted order is agreed first (Raul, 25 Nov 2026, INI-523),
 * noted in data.agreed_on_receiving. Suppliers often never use their portal, so nothing here waits for it.
 * Costing follows the usual costing reminder; goods in never sees prices.
 */
class ReceiveSupplierParcel extends OrgAction
{
    use WithGoodsInBookInAuthorisation;

    public static function unitsPerSko(PurchaseOrderTransaction $line): float
    {
        return (float) ($line->orgStock?->packed_in ?: 1);
    }

    /**
     * @param  array<int|string, float|int|string>  $skoQuantities  purchase order transaction id => SKOs that arrived
     */
    public function handle(Organisation $organisation, int $orgSupplierId, array $skoQuantities, ?User $user = null): StockDelivery
    {
        $skoQuantities = array_filter(array_map('floatval', $skoQuantities), fn (float $skos) => $skos > 0);
        if (!$skoQuantities) {
            throw ValidationException::withMessages(['lines' => __('Enter the SKOs that arrived for at least one line')]);
        }

        $lines = PurchaseOrderTransaction::whereIn('id', array_keys($skoQuantities))->with('orgStock')->get();
        $purchaseOrders = GetSupplierParcelPurchaseOrders::directOpenPurchaseOrders($organisation)
            ->where('purchase_orders.parent_id', $orgSupplierId)
            ->whereIn('purchase_orders.id', $lines->pluck('purchase_order_id'))
            ->orderBy('purchase_orders.id')
            ->get();

        if ($lines->count() !== count($skoQuantities) || $lines->pluck('purchase_order_id')->diff($purchaseOrders->pluck('id'))->isNotEmpty()) {
            throw ValidationException::withMessages(['lines' => __('A line is not on an open order of this supplier')]);
        }

        if ($purchaseOrders->pluck('currency_id')->unique()->count() > 1) {
            throw ValidationException::withMessages(['lines' => __('These orders are in different currencies: receive each order\'s lines on its own')]);
        }

        return DB::transaction(function () use ($purchaseOrders, $lines, $skoQuantities, $user) {
            $stockDelivery = null;

            foreach ($purchaseOrders as $purchaseOrder) {
                $awaiting      = GetSupplierParcelPurchaseOrders::receivableLines($purchaseOrder)->pluck('quantity_ready_to_ship', 'id');
                $purchaseOrder = $this->agreeIfSubmitted($purchaseOrder, $user);
                $orderLines    = $lines->where('purchase_order_id', $purchaseOrder->id);

                $stockDelivery = StoreStockDeliveryFromPurchaseOrder::make()->action($purchaseOrder, array_filter([
                    'purchase_order_transaction_ids' => $orderLines->pluck('id')->values()->all(),
                    'unit_quantities'                => $orderLines->mapWithKeys(fn (PurchaseOrderTransaction $line) => [
                        $line->id => self::units($skoQuantities[$line->id], $line, (float) ($awaiting[$line->id] ?? 0)),
                    ])->all(),
                    'stock_delivery_id'              => $stockDelivery?->id,
                ]));
            }

            return ReceiveStockDelivery::make()->action($stockDelivery);
        });
    }

    /**
     * SKOs typed to 3 decimals for an awaited 6.6667 (20 units in packs of 3) must not come out 20.001 units and be
     * refused: within a hundredth of an SKO of what is awaited, it is what is awaited.
     */
    public static function units(float $skos, PurchaseOrderTransaction $line, float $awaitingUnits): float
    {
        $units = round($skos * self::unitsPerSko($line), 4);

        return abs($units - $awaitingUnits) <= self::unitsPerSko($line) / 100 ? $awaitingUnits : $units;
    }

    private function agreeIfSubmitted(PurchaseOrder $purchaseOrder, ?User $user): PurchaseOrder
    {
        if ($purchaseOrder->state !== PurchaseOrderStateEnum::SUBMITTED) {
            return $purchaseOrder;
        }

        $purchaseOrder = UpdatePurchaseOrderStateToConfirmed::make()->action($purchaseOrder);
        $purchaseOrder->update([
            'data' => [
                ...($purchaseOrder->data ?? []),
                'agreed_on_receiving' => ['at' => now()->toDateTimeString(), 'user_id' => $user?->id],
            ],
        ]);

        return $purchaseOrder;
    }

    public function rules(): array
    {
        return [
            'org_supplier_id' => ['required', 'integer'],
            'skos'            => ['required', 'array', 'min:1'],
            'skos.*'          => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): StockDelivery
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($this->organisation, (int) $this->validatedData['org_supplier_id'], Arr::get($this->validatedData, 'skos', []), $request->user());
    }

    public function htmlResponse(StockDelivery $stockDelivery): RedirectResponse
    {
        return redirect()->route('grp.org.warehouses.show.incoming.stock_deliveries.show', [
            $stockDelivery->organisation->slug,
            $this->warehouse->slug,
            $stockDelivery->slug,
        ]);
    }
}
