<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-19 10:00
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\GoodsIn\StockDeliveryItem;

use App\Actions\GoodsIn\StockDelivery\AddPurchaseOrderItemsToStockDelivery;
use App\Actions\GoodsIn\StockDelivery\AddPurchaseOrdersToStockDelivery;
use App\Actions\GoodsIn\StockDelivery\Hydrators\StockDeliveriesHydrateItems;
use App\Actions\GoodsIn\StockDelivery\RemovePurchaseOrderFromStockDelivery;
use App\Actions\GoodsIn\StockDelivery\Traits\HasStockDeliveryHydrators;
use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrder\Hydrators\PurchaseOrderHydrateDeliveryState;
use App\Actions\Procurement\PurchaseOrder\Hydrators\PurchaseOrderHydrateTransactions;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryItem;
use App\Models\Procurement\PurchaseOrderTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * Sets how many units of a line go in a container in process. Fewer units go back to awaiting delivery on the
 * purchase order, the line keeping its unit price. More units are taken from what its purchase order line still has
 * awaiting delivery, through AddPurchaseOrderItemsToStockDelivery so the same caps apply. Taking every unit off is
 * RemoveStockDeliveryItemsFromStockDelivery.
 */
class ChangeStockDeliveryItemQuantity extends OrgAction
{
    use HasStockDeliveryHydrators;

    private StockDeliveryItem $stockDeliveryItem;

    public static function canBeChanged(StockDeliveryItem $stockDeliveryItem): bool
    {
        return $stockDeliveryItem->stockDelivery
            && RemovePurchaseOrderFromStockDelivery::acceptsRemovals($stockDeliveryItem->stockDelivery)
            && !in_array($stockDeliveryItem->state, [StockDeliveryItemStateEnum::CANCELLED, StockDeliveryItemStateEnum::NOT_RECEIVED], true)
            && (float) $stockDeliveryItem->unit_quantity > 0;
    }

    /**
     * Units each line of the container can still take from its purchase order line, keyed by stock delivery item id.
     *
     * @return array<int, float>
     */
    public static function addableUnitQuantities(StockDelivery $stockDelivery): array
    {
        if (!RemovePurchaseOrderFromStockDelivery::acceptsRemovals($stockDelivery)) {
            return [];
        }

        $awaitingByTransaction = AddPurchaseOrderItemsToStockDelivery::addableItems($stockDelivery)->get()
            ->mapWithKeys(fn (PurchaseOrderTransaction $transaction) => [$transaction->id => max(0, (float) $transaction->quantity_awaiting_delivery)]);

        return $stockDelivery->items()
            ->whereNotIn('state', [StockDeliveryItemStateEnum::CANCELLED, StockDeliveryItemStateEnum::NOT_RECEIVED])
            ->get(['id', 'data'])
            ->mapWithKeys(fn (StockDeliveryItem $item) => [$item->id => (float) ($awaitingByTransaction[(int) data_get($item->data, 'purchase_order_transaction_id')] ?? 0)])
            ->filter(fn (float $units) => $units > 0)
            ->all();
    }

    public static function addableUnitQuantity(StockDeliveryItem $stockDeliveryItem): float
    {
        return self::addableUnitQuantities($stockDeliveryItem->stockDelivery)[$stockDeliveryItem->id] ?? 0;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return AddPurchaseOrdersToStockDelivery::userCanAdd($this->stockDeliveryItem->stockDelivery, $request->user());
    }

    public function rules(): array
    {
        return [
            'unit_quantity' => ['required', 'numeric', 'gt:0'],
        ];
    }

    public function getValidationMessages(): array
    {
        return [
            'unit_quantity.gt' => __('To take every unit out of the container, remove the item'),
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if (!self::canBeChanged($this->stockDeliveryItem)) {
            $validator->errors()->add('unit_quantity', __('Quantities can only be changed in a container in process'));

            return;
        }

        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $unitQuantity = (float) $validator->getData()['unit_quantity'];
        $current      = (float) $this->stockDeliveryItem->unit_quantity;
        $addable      = self::addableUnitQuantity($this->stockDeliveryItem);

        if ($unitQuantity == $current) {
            $validator->errors()->add('unit_quantity', __('The container already has :units units of this item', ['units' => $current]));
        } elseif ($unitQuantity - $current > $addable + 0.0001) {
            $validator->errors()->add('unit_quantity', __('At most :units units can go in the container, the rest of the purchase order line is not awaiting delivery', [
                'units' => $current + $addable,
            ]));
        }
    }

    public function handle(StockDeliveryItem $stockDeliveryItem, float $unitQuantity): StockDeliveryItem
    {
        $stockDelivery = $stockDeliveryItem->stockDelivery;
        $current       = (float) $stockDeliveryItem->unit_quantity;
        $transaction   = PurchaseOrderTransaction::find(data_get($stockDeliveryItem->data, 'purchase_order_transaction_id'));

        if ($unitQuantity > $current) {
            AddPurchaseOrderItemsToStockDelivery::make()->action($stockDelivery, [
                'items' => [[
                    'purchase_order_transaction_id' => $transaction?->id,
                    'unit_quantity'                 => $unitQuantity - $current,
                ]],
            ]);
        } else {
            DB::transaction(function () use ($stockDelivery, $stockDeliveryItem, $unitQuantity) {
                StockDelivery::whereKey($stockDelivery->id)->lockForUpdate()->first();

                $stockDeliveryItem = StockDeliveryItem::lockForUpdate()->findOrFail($stockDeliveryItem->id);
                $netAmount         = (float) $stockDeliveryItem->net_amount * $unitQuantity / (float) $stockDeliveryItem->unit_quantity;

                $stockDeliveryItem->update([
                    'unit_quantity'  => $unitQuantity,
                    'net_amount'     => $netAmount,
                    'org_net_amount' => $netAmount * (float) ($stockDeliveryItem->org_exchange ?? 1),
                    'grp_net_amount' => $netAmount * (float) ($stockDeliveryItem->grp_exchange ?? 1),
                ]);
            });

            if ($transaction?->purchaseOrder) {
                PurchaseOrderHydrateTransactions::dispatch($transaction->purchaseOrder);
                PurchaseOrderHydrateDeliveryState::run($transaction->purchaseOrder);
            }

            StockDeliveriesHydrateItems::run($stockDelivery);
        }

        $this->runStockDeliveryHydrators($stockDelivery);

        return $stockDeliveryItem->refresh();
    }

    public function asController(StockDeliveryItem $stockDeliveryItem, ActionRequest $request): StockDeliveryItem
    {
        $this->stockDeliveryItem = $stockDeliveryItem;
        $this->initialisation($stockDeliveryItem->organisation, $request);

        return $this->handle($stockDeliveryItem, (float) $this->validatedData['unit_quantity']);
    }

    public function action(StockDeliveryItem $stockDeliveryItem, float $unitQuantity): StockDeliveryItem
    {
        $this->asAction          = true;
        $this->stockDeliveryItem = $stockDeliveryItem;
        $this->initialisation($stockDeliveryItem->organisation, ['unit_quantity' => $unitQuantity]);

        return $this->handle($stockDeliveryItem, (float) $this->validatedData['unit_quantity']);
    }

    public function htmlResponse(): RedirectResponse
    {
        return redirect()->back();
    }
}
