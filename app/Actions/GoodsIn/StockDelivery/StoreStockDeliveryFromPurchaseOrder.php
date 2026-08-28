<?php

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\Traits\Authorisations\WithProcurementEditAuthorisation;
use App\Actions\GoodsIn\StockDelivery\Hydrators\StockDeliveriesHydrateItems;
use App\Actions\GoodsIn\StockDeliveryItem\StoreStockDeliveryItem;
use App\Actions\Procurement\PurchaseOrder\Hydrators\PurchaseOrderHydrateTransactions;
use App\Actions\Helpers\SerialReference\GetSerialReference;
use App\Actions\OrgAction;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Enums\Helpers\SerialReference\SerialReferenceModelEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Http\Resources\Procurement\StockDeliveryResource;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreStockDeliveryFromPurchaseOrder extends OrgAction
{
    use WithProcurementEditAuthorisation;
    use AsAction;

    private PurchaseOrder $purchaseOrder;

    public function handle(PurchaseOrder $purchaseOrder, array $modelData = []): StockDelivery
    {
        if ($purchaseOrder->state !== PurchaseOrderStateEnum::CONFIRMED) {
            abort(422, __('Only confirmed purchase orders can create a stock delivery'));
        }

        $purchaseOrderTransactionsQuery = $purchaseOrder->purchaseOrderTransactions()
            ->where('state', PurchaseOrderTransactionStateEnum::CONFIRMED)
            ->with(['historicSupplierProduct', 'orgStock']);

        if (array_key_exists('purchase_order_transaction_ids', $modelData)) {
            $purchaseOrderTransactionsQuery->whereIn('id', $modelData['purchase_order_transaction_ids']);
        }

        $purchaseOrderTransactions = $purchaseOrderTransactionsQuery->get();

        if ($purchaseOrderTransactions->isEmpty()) {
            throw ValidationException::withMessages([
                'purchase_order_transaction_ids' => __('Select at least one purchase order item'),
            ]);
        }

        $stockDelivery = StoreStockDelivery::make()->action(
            $purchaseOrder->parent,
            array_merge([
                'reference'   => GetSerialReference::run(
                    container: $purchaseOrder->organisation,
                    modelType: SerialReferenceModelEnum::STOCK_DELIVERY
                ),
                'state'       => StockDeliveryStateEnum::IN_PROCESS,
                'date'        => now(),
                'currency_id' => $purchaseOrder->currency_id,
                'data'        => $this->getStockDeliveryData($purchaseOrder),
            ], $this->getExchanges($purchaseOrder)),
            strict: false
        );

        $stockDelivery->purchaseOrders()->attach($purchaseOrder->id);
        $stockDelivery->update([
            'number_purchase_orders' => $stockDelivery->purchaseOrders()->count(),
        ]);

        $purchaseOrder->update([
            'delivery_state' => PurchaseOrderDeliveryStateEnum::from($stockDelivery->state->value),
        ]);

        foreach ($purchaseOrderTransactions as $purchaseOrderTransaction) {
            StoreStockDeliveryItem::run(
                $stockDelivery,
                $purchaseOrderTransaction->historicSupplierProduct,
                $purchaseOrderTransaction->orgStock,
                array_merge([
                    'state'         => StockDeliveryItemStateEnum::IN_PROCESS,
                    'unit_quantity' => $purchaseOrderTransaction->quantity_ordered,
                    'net_amount'    => $purchaseOrderTransaction->net_amount,
                    'data'          => [
                        'purchase_order_transaction_id' => $purchaseOrderTransaction->id,
                    ],
                ], $this->getExchanges($purchaseOrderTransaction))
            );
        }

        $purchaseOrder->purchaseOrderTransactions()
            ->whereIn('id', $purchaseOrderTransactions->modelKeys())
            ->where('delivery_state', '!=', PurchaseOrderTransactionDeliveryStateEnum::IN_PROCESS)
            ->update(['delivery_state' => PurchaseOrderTransactionDeliveryStateEnum::IN_PROCESS]);

        PurchaseOrderHydrateTransactions::dispatch($purchaseOrder);

        StockDeliveriesHydrateItems::dispatch($stockDelivery);

        return $stockDelivery->refresh();
    }

    public function asController(PurchaseOrder $purchaseOrder, ActionRequest $request): StockDelivery
    {
        $this->purchaseOrder = $purchaseOrder;
        $this->initialisation($purchaseOrder->organisation, $request);

        return $this->handle($purchaseOrder, $this->validatedData);
    }

    public function rules(): array
    {
        return [
            'purchase_order_transaction_ids'   => ['sometimes', 'array', 'min:1'],
            'purchase_order_transaction_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('purchase_order_transactions', 'id')->where(
                    fn ($query) => $query
                        ->where('purchase_order_id', $this->purchaseOrder->id)
                        ->where('state', PurchaseOrderTransactionStateEnum::CONFIRMED->value)
                ),
            ],
        ];
    }

    public function action(PurchaseOrder $purchaseOrder, array $modelData = []): StockDelivery
    {
        $this->asAction = true;
        $this->purchaseOrder = $purchaseOrder;
        $this->initialisation($purchaseOrder->organisation, $modelData);

        return $this->handle($purchaseOrder, $this->validatedData);
    }

    public function htmlResponse(StockDelivery $stockDelivery): RedirectResponse
    {
        return Redirect::route('grp.org.procurement.stock_deliveries.show', [
            'organisation' => $stockDelivery->organisation->slug,
            'stockDelivery' => $stockDelivery->slug,
        ]);
    }

    public function jsonResponse(StockDelivery $stockDelivery): StockDeliveryResource
    {
        return new StockDeliveryResource($stockDelivery);
    }

    private function getExchanges(PurchaseOrder|PurchaseOrderTransaction $model): array
    {
        return array_filter([
            'org_exchange' => $model->org_exchange,
            'grp_exchange' => $model->grp_exchange,
        ], fn ($exchange) => $exchange !== null);
    }

    private function getStockDeliveryData(PurchaseOrder $purchaseOrder): array
    {
        $data = $purchaseOrder->data ?? [];

        return [
            'delivery_type'             => Arr::get($data, 'delivery_type'),
            'estimated_dispatched_date' => Arr::get($data, 'estimated_production_date'),
            'estimated_receiving_date'  => Arr::get($data, 'estimated_receiving_date'),
            'incoterm'                  => Arr::get($data, 'incoterm'),
            'port_of_export'            => Arr::get($data, 'port_of_export'),
            'port_of_import'            => Arr::get($data, 'port_of_import'),
            'delivery_address'          => Arr::get($data, 'delivery_address'),
        ];
    }
}
