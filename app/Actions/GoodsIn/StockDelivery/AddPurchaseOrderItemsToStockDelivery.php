<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-10-15 14:35
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\OrgAction;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * An agent fills a container line by line: any item of the confirmed purchase orders it may add, each with as many
 * of its units still awaiting delivery as fit, the rest staying on the purchase order for another container.
 */
class AddPurchaseOrderItemsToStockDelivery extends OrgAction
{
    use AsAction;

    private StockDelivery $stockDelivery;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return AddPurchaseOrdersToStockDelivery::userCanAdd($this->stockDelivery, $request->user());
    }

    /**
     * From an agent warehouse quantity_awaiting_delivery is what was received there and is not in a shipment yet,
     * the most that can be added.
     *
     * @return Builder<PurchaseOrderTransaction>
     */
    public static function addableItems(StockDelivery $stockDelivery): Builder
    {
        $purchaseOrderIds   = AddPurchaseOrdersToStockDelivery::addablePurchaseOrders($stockDelivery)->pluck('purchase_orders.id');
        $fromAgentWarehouse = AddPurchaseOrdersToStockDelivery::shipsFromAgentWarehouse($stockDelivery);

        $items = StoreStockDeliveryFromPurchaseOrder::whereReadyToShip(
            PurchaseOrderTransaction::query()->whereIn('purchase_order_transactions.purchase_order_id', $purchaseOrderIds),
            $fromAgentWarehouse
        );

        if (!$fromAgentWarehouse) {
            return StoreStockDeliveryFromPurchaseOrder::withQuantityAwaitingDelivery($items);
        }

        $received   = StoreStockDeliveryFromPurchaseOrder::quantityReceivedAtAgentWarehouse();
        $onDelivery = StoreStockDeliveryFromPurchaseOrder::quantityOnDelivery();

        return $items->select('purchase_order_transactions.*')->selectRaw(
            'least(purchase_order_transactions.quantity_ordered, ('.$received->toSql().')) - ('.$onDelivery->toSql().') as quantity_awaiting_delivery',
            [...$received->getBindings(), ...$onDelivery->getBindings()]
        );
    }

    /**
     * An addable line in units, with what one SKO and one carton hold so a page can show the quantity each way. Needs
     * purchaseOrder, supplierProduct and orgStock loaded.
     *
     * @return array{id: int, purchase_order_id: int, purchase_order_reference: string, supplier: ?string, code: ?string, name: ?string, quantity_ordered: float, quantity_awaiting_delivery: float, units_per_sko: float, units_per_carton: float}
     */
    public static function addableItemRow(PurchaseOrderTransaction $transaction): array
    {
        return [
            'id'                         => $transaction->id,
            'purchase_order_id'          => $transaction->purchase_order_id,
            'purchase_order_reference'   => $transaction->purchaseOrder->reference,
            'supplier'                   => $transaction->purchaseOrder->parent_name,
            'code'                       => $transaction->supplierProduct?->code ?? $transaction->orgStock?->code,
            'name'                       => $transaction->supplierProduct?->name ?? $transaction->orgStock?->name,
            'quantity_ordered'           => (float) $transaction->quantity_ordered,
            'quantity_awaiting_delivery' => (float) $transaction->quantity_awaiting_delivery,
            'units_per_sko'              => (float) (($transaction->supplierProduct ? $transaction->supplierProduct->units_per_pack : $transaction->orgStock?->packed_in) ?: 1),
            'units_per_carton'           => (float) (($transaction->supplierProduct ? $transaction->supplierProduct->units_per_carton : $transaction->orgStock?->packed_in) ?: 0),
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if (!AddPurchaseOrdersToStockDelivery::acceptsPurchaseOrders($this->stockDelivery)) {
            $validator->errors()->add('items', __('Items can only be added to an agent container in process'));
        }
    }

    public function rules(): array
    {
        return [
            'items'                                 => ['required', 'array', 'min:1'],
            'items.*.purchase_order_transaction_id' => [
                'required',
                'integer',
                'distinct',
                Rule::in(self::addableItems($this->stockDelivery)->pluck('purchase_order_transactions.id')->all()),
            ],
            'items.*.unit_quantity'                 => ['required', 'numeric', 'gt:0'],
        ];
    }

    public function getValidationMessages(): array
    {
        return [
            'items.*.purchase_order_transaction_id.in' => __('This item can not be added to this container, it is no longer awaiting delivery or its purchase order is in another currency'),
            'items.*.unit_quantity.gt'                 => __('Enter how many units of the item go in the container'),
        ];
    }

    public function handle(StockDelivery $stockDelivery, array $modelData): StockDelivery
    {
        /** @var Collection<int, float> $unitQuantities */
        $unitQuantities = collect($modelData['items'])->mapWithKeys(fn (array $item) => [(int) $item['purchase_order_transaction_id'] => (float) $item['unit_quantity']]);

        DB::transaction(function () use ($stockDelivery, $unitQuantities) {
            PurchaseOrderTransaction::whereIn('id', $unitQuantities->keys())
                ->get(['id', 'purchase_order_id'])
                ->groupBy('purchase_order_id')
                ->sortKeys()
                ->each(function (Collection $transactions, int $purchaseOrderId) use ($stockDelivery, $unitQuantities) {
                    $transactionIds = $transactions->pluck('id')->all();

                    StoreStockDeliveryFromPurchaseOrder::make()->action(PurchaseOrder::findOrFail($purchaseOrderId), [
                        'stock_delivery_id'              => $stockDelivery->id,
                        'purchase_order_transaction_ids' => $transactionIds,
                        'unit_quantities'                => $unitQuantities->only($transactionIds)->all(),
                    ]);
                });
        });

        return $stockDelivery->refresh();
    }

    public function asController(StockDelivery $stockDelivery, ActionRequest $request): StockDelivery
    {
        $this->stockDelivery = $stockDelivery;
        $this->initialisation($stockDelivery->organisation, $request);

        return $this->handle($stockDelivery, $this->validatedData);
    }

    public function action(StockDelivery $stockDelivery, array $modelData): StockDelivery
    {
        $this->asAction      = true;
        $this->stockDelivery = $stockDelivery;
        $this->initialisation($stockDelivery->organisation, $modelData);

        return $this->handle($stockDelivery, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return redirect()->back();
    }
}
