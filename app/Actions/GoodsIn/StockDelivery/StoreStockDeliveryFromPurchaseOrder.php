<?php

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\Procurement\WithProcurementSerialReferences;
use App\Actions\Traits\Authorisations\WithProcurementEditAuthorisation;
use App\Actions\GoodsIn\StockDelivery\Hydrators\StockDeliveriesHydrateItems;
use App\Actions\GoodsIn\StockDeliveryItem\StoreStockDeliveryItem;
use App\Actions\Procurement\PurchaseOrder\Hydrators\PurchaseOrderHydrateTransactions;
use App\Actions\OrgAction;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Enums\Helpers\SerialReference\SerialReferenceModelEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Http\Resources\Procurement\StockDeliveryResource;
use App\Models\Catalogue\Shop;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreStockDeliveryFromPurchaseOrder extends OrgAction
{
    use WithProcurementEditAuthorisation;
    use AsAction;
    use WithProcurementSerialReferences;

    private PurchaseOrder $purchaseOrder;

    public function handle(PurchaseOrder $purchaseOrder, array $modelData = []): StockDelivery
    {
        if ($purchaseOrder->state !== PurchaseOrderStateEnum::CONFIRMED) {
            abort(422, __('Only confirmed purchase orders can create a stock delivery'));
        }

        $partnerDeliveryBlockedReason = self::partnerDeliveryBlockedReason($purchaseOrder);
        if ($partnerDeliveryBlockedReason) {
            throw ValidationException::withMessages([
                'purchase_order_transaction_ids' => $partnerDeliveryBlockedReason,
            ]);
        }

        $openAuroraStockDelivery = $purchaseOrder->stockDeliveries()
            ->whereNotNull('source_id')
            ->whereNotIn('state', [
                StockDeliveryStateEnum::BOOKED_IN,
                StockDeliveryStateEnum::PLACED,
                StockDeliveryStateEnum::CANCELLED,
                StockDeliveryStateEnum::NOT_RECEIVED,
            ])
            ->first();

        if ($openAuroraStockDelivery) {
            throw ValidationException::withMessages([
                'purchase_order_transaction_ids' => __('This purchase order already has the stock delivery :reference, book the goods in on it', ['reference' => $openAuroraStockDelivery->reference]),
            ]);
        }

        $stockDelivery = DB::transaction(function () use ($purchaseOrder, $modelData) {
            PurchaseOrder::whereKey($purchaseOrder->id)->lockForUpdate()->first();

            $purchaseOrderTransactionsQuery = self::transactionsAwaitingDelivery($purchaseOrder)
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

            $deliveryParent = $purchaseOrder->isAgentOrder() ? $purchaseOrder->orgAgentOfOrder() : $purchaseOrder->parent;
            if (!$deliveryParent) {
                throw ValidationException::withMessages(['purchase_order_transaction_ids' => __('This organisation no longer buys through the agent of this order')]);
            }

            if ($stockDeliveryId = Arr::get($modelData, 'stock_delivery_id')) {
                $stockDelivery = StockDelivery::whereKey($stockDeliveryId)->lockForUpdate()->firstOrFail();
            } else {
                $stockDelivery = StoreStockDelivery::make()->action(
                    $deliveryParent,
                    array_merge([
                        'reference'   => $this->newProcurementReference($deliveryParent, SerialReferenceModelEnum::STOCK_DELIVERY),
                        'state'       => StockDeliveryStateEnum::IN_PROCESS,
                        'date'        => now(),
                        'currency_id' => $purchaseOrder->currency_id,
                        'data'        => $this->getStockDeliveryData($purchaseOrder),
                    ], $this->getExchanges($purchaseOrder)),
                    strict: false
                );
            }

            $stockDelivery->purchaseOrders()->syncWithoutDetaching([$purchaseOrder->id]);
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

            return $stockDelivery;
        });

        PurchaseOrderHydrateTransactions::dispatch($purchaseOrder);

        StockDeliveriesHydrateItems::dispatch($stockDelivery);

        return $stockDelivery->refresh();
    }

    public static function transactionsAwaitingDelivery(PurchaseOrder $purchaseOrder): HasMany
    {
        return $purchaseOrder->purchaseOrderTransactions()
            ->where('state', PurchaseOrderTransactionStateEnum::CONFIRMED)
            ->whereNotExists(fn (Builder $items) => self::liveDeliveryItemsOfTransaction($items));
    }

    public static function liveDeliveryItemsOfTransaction(Builder $items): Builder
    {
        return self::deliveryItemsOfTransaction($items)
            ->join('purchase_order_stock_delivery', 'purchase_order_stock_delivery.stock_delivery_id', 'stock_delivery_items.stock_delivery_id')
            ->whereColumn('purchase_order_stock_delivery.purchase_order_id', 'purchase_order_transactions.purchase_order_id')
            ->whereNotIn('stock_delivery_items.state', [StockDeliveryItemStateEnum::CANCELLED->value, StockDeliveryItemStateEnum::NOT_RECEIVED->value]);
    }

    public static function deliveryItemsOfTransaction(Builder $items): Builder
    {
        return $items->from('stock_delivery_items')
            ->where(function (Builder $match) {
                $match->whereRaw("(stock_delivery_items.data->>'purchase_order_transaction_id')::bigint = purchase_order_transactions.id")
                    ->orWhere(function (Builder $legacy) {
                        $legacy->whereRaw("stock_delivery_items.data->>'purchase_order_transaction_id' is null")
                            ->whereColumn('stock_delivery_items.org_stock_id', 'purchase_order_transactions.org_stock_id');
                    });
            });
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
            'stock_delivery_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::in(self::openAgentDeliveries($this->purchaseOrder)->pluck('id')->all()),
            ],
        ];
    }

    /**
     * An agent ships the orders of many of its suppliers in one container, so an agent order can
     * join a delivery the agent already has in process, as long as both are in the same currency.
     *
     * @return Collection<int, StockDelivery>
     */
    public static function openAgentDeliveries(PurchaseOrder $purchaseOrder): Collection
    {
        $orgAgent = $purchaseOrder->isAgentOrder() ? $purchaseOrder->orgAgentOfOrder() : null;
        if (!$orgAgent) {
            return new Collection();
        }

        return StockDelivery::query()
            ->where('parent_type', 'OrgAgent')
            ->where('parent_id', $orgAgent->id)
            ->where('state', StockDeliveryStateEnum::IN_PROCESS)
            ->where('currency_id', $purchaseOrder->currency_id)
            ->orderByDesc('id')
            ->get(['id', 'reference', 'slug', 'date', 'number_purchase_orders']);
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

    public static function partnerDeliveryBlockedReason(PurchaseOrder $purchaseOrder): ?string
    {
        if (!self::partnerCreatesItsOwnDeliveries($purchaseOrder)) {
            return null;
        }

        return __(':partner deliveries are created automatically when :partner dispatches the order, book the goods in on it in Procurement > Partners', ['partner' => $purchaseOrder->parent->partner->name]);
    }

    private static function partnerCreatesItsOwnDeliveries(PurchaseOrder $purchaseOrder): bool
    {
        if (!$purchaseOrder->parent instanceof OrgPartner) {
            return false;
        }

        $sellerShopIds = array_keys(data_get($purchaseOrder->parent->data, 'intercompany_customers', []));
        if (!$sellerShopIds) {
            return false;
        }

        $shops = Shop::whereIn('id', $sellerShopIds)->get(['migrated_to_aiku_on']);
        if ($shops->contains(fn (Shop $shop) => !$shop->migrated_to_aiku_on)) {
            return false;
        }

        return $purchaseOrder->created_at->gte($shops->max('migrated_to_aiku_on'));
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
