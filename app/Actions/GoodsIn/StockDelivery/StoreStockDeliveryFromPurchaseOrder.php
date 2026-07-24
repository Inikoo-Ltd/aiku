<?php

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\GoodsIn\StockDelivery\Hydrators\StockDeliveriesHydrateItems;
use App\Actions\GoodsIn\StockDeliveryItem\StoreStockDeliveryItem;
use App\Actions\Helpers\SerialReference\GetSerialReference;
use App\Actions\OrgAction;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Enums\Helpers\SerialReference\SerialReferenceModelEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Http\Resources\Procurement\StockDeliveryResource;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreStockDeliveryFromPurchaseOrder extends OrgAction
{
    use AsAction;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("procurement.{$this->organisation->id}.edit");
    }

    public function handle(PurchaseOrder $purchaseOrder): StockDelivery
    {
        if ($purchaseOrder->state !== PurchaseOrderStateEnum::CONFIRMED) {
            abort(422, __('Only confirmed purchase orders can create a stock delivery'));
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

        $purchaseOrderTransactions = $purchaseOrder->purchaseOrderTransactions()
            ->where('state', '!=', PurchaseOrderTransactionStateEnum::CANCELLED)
            ->get();

        foreach ($purchaseOrderTransactions as $purchaseOrderTransaction) {
            StoreStockDeliveryItem::run(
                $stockDelivery,
                $purchaseOrderTransaction->historicSupplierProduct,
                $purchaseOrderTransaction->orgStock,
                array_merge([
                    'state'         => StockDeliveryItemStateEnum::IN_PROCESS,
                    'unit_quantity' => $purchaseOrderTransaction->quantity_ordered,
                    'net_amount'    => $purchaseOrderTransaction->net_amount,
                ], $this->getExchanges($purchaseOrderTransaction))
            );
        }

        StockDeliveriesHydrateItems::dispatch($stockDelivery);

        return $stockDelivery->refresh();
    }

    public function asController(PurchaseOrder $purchaseOrder, ActionRequest $request): StockDelivery
    {
        $this->initialisation($purchaseOrder->organisation, $request);

        return $this->handle($purchaseOrder);
    }

    public function action(PurchaseOrder $purchaseOrder): StockDelivery
    {
        $this->asAction = true;
        $this->initialisation($purchaseOrder->organisation, []);

        return $this->handle($purchaseOrder);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
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
