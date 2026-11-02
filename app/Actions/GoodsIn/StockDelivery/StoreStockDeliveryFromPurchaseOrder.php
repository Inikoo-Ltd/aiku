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
use App\Enums\Procurement\AgentGoodsReceipt\AgentGoodsReceiptStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Http\Resources\Procurement\StockDeliveryResource;
use App\Models\Catalogue\Shop;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryItem;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
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

    /**
     * Units left over from converting SKOs back to units, below which a line counts as all in containers.
     */
    public const float QUANTITY_TOLERANCE = 0.01;

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

        $sampleBlockedReason = self::sampleBlockedReason($purchaseOrder);
        if ($sampleBlockedReason) {
            throw ValidationException::withMessages([
                'purchase_order_transaction_ids' => $sampleBlockedReason,
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

            $fromAgentWarehouse        = self::shipsFromAgentWarehouse($purchaseOrder);
            $purchaseOrderTransactions = self::withQuantityReadyToShip($purchaseOrderTransactionsQuery, $fromAgentWarehouse)->get();
            $requestedUnitQuantities   = Arr::get($modelData, 'unit_quantities', []);

            foreach ($purchaseOrderTransactions as $purchaseOrderTransaction) {
                $requested = Arr::get($requestedUnitQuantities, $purchaseOrderTransaction->id);
                if ($requested !== null && (float) $requested > max(0, (float) $purchaseOrderTransaction->quantity_ready_to_ship)) {
                    $replacements = [
                        'units' => max(0, (float) $purchaseOrderTransaction->quantity_ready_to_ship),
                        'code'  => $purchaseOrderTransaction->historicSupplierProduct?->code ?? $purchaseOrderTransaction->orgStock?->code,
                    ];

                    throw ValidationException::withMessages([
                        'unit_quantities' => $fromAgentWarehouse
                            ? __('Only :units units of :code were received at the agent\'s warehouse and are not in a shipment yet', $replacements)
                            : __('Only :units units of :code are still awaiting delivery', $replacements),
                    ]);
                }
            }

            if ($fromAgentWarehouse) {
                $purchaseOrderTransactions = $purchaseOrderTransactions->filter(
                    fn (PurchaseOrderTransaction $purchaseOrderTransaction) => (float) $purchaseOrderTransaction->quantity_ready_to_ship > self::QUANTITY_TOLERANCE
                );
            }

            if ($purchaseOrderTransactions->isEmpty()) {
                throw ValidationException::withMessages([
                    'purchase_order_transaction_ids' => $fromAgentWarehouse
                        ? __('Nothing of this order is at the agent\'s warehouse waiting for a shipment: record the supplier\'s delivery as a goods receipt first')
                        : __('Select at least one purchase order item'),
                ]);
            }

            $deliveryParent = $purchaseOrder->isAgentOrder() ? $purchaseOrder->orgAgentOfOrder() : $purchaseOrder->parent;
            if (!$deliveryParent) {
                throw ValidationException::withMessages(['purchase_order_transaction_ids' => __('This organisation no longer buys through the agent of this order')]);
            }

            if ($stockDeliveryId = Arr::get($modelData, 'stock_delivery_id')) {
                $stockDelivery = StockDelivery::whereKey($stockDeliveryId)->lockForUpdate()->firstOrFail();
                $this->fillEmptyDeliveryData($stockDelivery, $purchaseOrder);
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
                $unitQuantity = (float) Arr::get($requestedUnitQuantities, $purchaseOrderTransaction->id, $purchaseOrderTransaction->quantity_ready_to_ship);
                $netAmount    = $unitQuantity == (float) $purchaseOrderTransaction->quantity_ordered
                    ? (float) $purchaseOrderTransaction->net_amount
                    : (float) $purchaseOrderTransaction->net_amount * $unitQuantity / (float) $purchaseOrderTransaction->quantity_ordered;

                $itemInThisDelivery = $stockDelivery->items()
                    ->whereRaw("(data->>'purchase_order_transaction_id')::bigint = ?", [$purchaseOrderTransaction->id])
                    ->whereNotIn('state', [StockDeliveryItemStateEnum::CANCELLED, StockDeliveryItemStateEnum::NOT_RECEIVED])
                    ->first();

                if ($itemInThisDelivery) {
                    $this->addToDeliveryItem($itemInThisDelivery, $unitQuantity, $netAmount);

                    continue;
                }

                StoreStockDeliveryItem::run(
                    $stockDelivery,
                    $purchaseOrderTransaction->historicSupplierProduct,
                    $purchaseOrderTransaction->orgStock,
                    array_merge([
                        'state'         => StockDeliveryItemStateEnum::IN_PROCESS,
                        'unit_quantity' => $unitQuantity,
                        'net_amount'    => $netAmount,
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

        StockDeliveriesHydrateItems::run($stockDelivery);

        rescue(fn () => PrefillDomesticStockDeliveryCosts::run($stockDelivery));

        return $stockDelivery->refresh();
    }

    public static function transactionsAwaitingDelivery(PurchaseOrder $purchaseOrder): HasMany
    {
        return self::whereAwaitingDelivery($purchaseOrder->purchaseOrderTransactions());
    }

    /**
     * A confirmed line is awaiting delivery while part of what was ordered is in no container yet: an agent can
     * ship a line across several containers.
     */
    public static function whereAwaitingDelivery(EloquentBuilder|HasMany $transactions): EloquentBuilder|HasMany
    {
        $quantityOnDelivery = self::quantityOnDelivery();

        return $transactions
            ->where('purchase_order_transactions.state', PurchaseOrderTransactionStateEnum::CONFIRMED)
            ->where(fn ($query) => $query
                ->whereNotExists(fn (Builder $items) => self::liveDeliveryItemsOfTransaction($items))
                ->orWhereRaw('purchase_order_transactions.quantity_ordered - ('.$quantityOnDelivery->toSql().') > '.self::QUANTITY_TOLERANCE, $quantityOnDelivery->getBindings()));
    }

    public static function withQuantityAwaitingDelivery(EloquentBuilder|HasMany $transactions): EloquentBuilder|HasMany
    {
        $quantityOnDelivery = self::quantityOnDelivery();

        return $transactions
            ->select('purchase_order_transactions.*')
            ->selectRaw('purchase_order_transactions.quantity_ordered - ('.$quantityOnDelivery->toSql().') as quantity_awaiting_delivery', $quantityOnDelivery->getBindings());
    }

    /**
     * An agent that receives goods in its own warehouse ships only what its goods receipts accepted there, so the
     * goods of every supplier are seen arriving at the agent instead of appearing in a shipment at the last minute
     * (HELP-4292).
     */
    public static function shipsFromAgentWarehouse(PurchaseOrder $purchaseOrder): bool
    {
        return $purchaseOrder->isAgentOrder() && (bool) $purchaseOrder->agent?->receivesGoodsInOwnWarehouse();
    }

    /**
     * Lines with units that can go in a shipment now: awaiting delivery and, from an agent warehouse, received there
     * and not in a shipment yet.
     */
    public static function whereReadyToShip(EloquentBuilder|HasMany $transactions, bool $fromAgentWarehouse): EloquentBuilder|HasMany
    {
        $transactions = self::whereAwaitingDelivery($transactions);
        if (!$fromAgentWarehouse) {
            return $transactions;
        }

        $received   = self::quantityReceivedAtAgentWarehouse();
        $onDelivery = self::quantityOnDelivery();

        return $transactions->whereRaw(
            '('.$received->toSql().') - ('.$onDelivery->toSql().') > '.self::QUANTITY_TOLERANCE,
            [...$received->getBindings(), ...$onDelivery->getBindings()]
        );
    }

    /**
     * Adds quantity_ready_to_ship next to quantity_awaiting_delivery: the units of each line that can go in a shipment now.
     */
    public static function withQuantityReadyToShip(EloquentBuilder|HasMany $transactions, bool $fromAgentWarehouse): EloquentBuilder|HasMany
    {
        $transactions = self::withQuantityAwaitingDelivery($transactions);
        $onDelivery   = self::quantityOnDelivery();

        if (!$fromAgentWarehouse) {
            return $transactions->selectRaw('purchase_order_transactions.quantity_ordered - ('.$onDelivery->toSql().') as quantity_ready_to_ship', $onDelivery->getBindings());
        }

        $received = self::quantityReceivedAtAgentWarehouse();

        return $transactions->selectRaw(
            'least(purchase_order_transactions.quantity_ordered, ('.$received->toSql().')) - ('.$onDelivery->toSql().') as quantity_ready_to_ship',
            [...$received->getBindings(), ...$onDelivery->getBindings()]
        );
    }

    /**
     * The units of a purchase order line the agent's goods receipts accepted, rejects left out, in a subquery
     * correlated to purchase_order_transactions.
     */
    public static function quantityReceivedAtAgentWarehouse(): Builder
    {
        return DB::table('agent_goods_receipt_items')
            ->join('agent_goods_receipts', 'agent_goods_receipts.id', 'agent_goods_receipt_items.agent_goods_receipt_id')
            ->where('agent_goods_receipts.state', AgentGoodsReceiptStateEnum::RECEIVED->value)
            ->whereColumn('agent_goods_receipt_items.purchase_order_transaction_id', 'purchase_order_transactions.id')
            ->selectRaw('coalesce(sum(agent_goods_receipt_items.quantity - coalesce(agent_goods_receipt_items.quantity_rejected, 0)), 0)');
    }

    /**
     * The units of a purchase order line already in a container, in a subquery correlated to purchase_order_transactions.
     */
    public static function quantityOnDelivery(): Builder
    {
        return self::liveDeliveryItemsOfTransaction(DB::query())
            ->selectRaw('coalesce(sum(stock_delivery_items.unit_quantity), 0)');
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
            'unit_quantities'                  => ['sometimes', 'array'],
            'unit_quantities.*'                => ['required', 'numeric', 'gt:0'],
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
        return self::openAgentDeliveriesQuery($purchaseOrder)->get(['id', 'reference', 'slug', 'date', 'number_purchase_orders']);
    }

    /**
     * @return EloquentBuilder<StockDelivery>
     */
    public static function openAgentDeliveriesQuery(PurchaseOrder $purchaseOrder): EloquentBuilder
    {
        $orgAgent = $purchaseOrder->isAgentOrder() ? $purchaseOrder->orgAgentOfOrder() : null;

        return StockDelivery::query()
            ->when(!$orgAgent, fn (EloquentBuilder $query) => $query->whereRaw('false'))
            ->where('parent_type', 'OrgAgent')
            ->where('parent_id', $orgAgent?->id)
            ->where('state', StockDeliveryStateEnum::IN_PROCESS)
            ->where('currency_id', $purchaseOrder->currency_id)
            ->orderByDesc('id');
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

    /**
     * An order whose sample must be approved first ships once it is, or once the buyer recorded on the order why it ships without.
     */
    public static function sampleBlockedReason(PurchaseOrder $purchaseOrder): ?string
    {
        if (!$purchaseOrder->isWaitingForSample()) {
            return null;
        }

        return __(':reference: its sample is not approved yet. Approve the sample, or record on the purchase order why it ships without one', ['reference' => $purchaseOrder->reference]);
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

    private function addToDeliveryItem(StockDeliveryItem $stockDeliveryItem, float $unitQuantity, float $netAmount): void
    {
        $netAmount = (float) $stockDeliveryItem->net_amount + $netAmount;

        $stockDeliveryItem->update([
            'unit_quantity'  => (float) $stockDeliveryItem->unit_quantity + $unitQuantity,
            'net_amount'     => $netAmount,
            'org_net_amount' => $netAmount * (float) ($stockDeliveryItem->org_exchange ?? 1),
            'grp_net_amount' => $netAmount * (float) ($stockDeliveryItem->grp_exchange ?? 1),
        ]);
    }

    private function fillEmptyDeliveryData(StockDelivery $stockDelivery, PurchaseOrder $purchaseOrder): void
    {
        $data = $stockDelivery->data ?? [];

        foreach ($this->getStockDeliveryData($purchaseOrder) as $key => $value) {
            if (blank(Arr::get($data, $key)) && filled($value)) {
                $data[$key] = $value;
            }
        }

        if ($data !== ($stockDelivery->data ?? [])) {
            $stockDelivery->update(['data' => $data]);
        }
    }

    private function getStockDeliveryData(PurchaseOrder $purchaseOrder): array
    {
        $data = $purchaseOrder->data ?? [];

        return [
            'delivery_type'             => Arr::get($data, 'delivery_type'),
            'tracking_number'           => Arr::get($data, 'parcel_reference'),
            'estimated_dispatched_date' => Arr::get($data, 'estimated_production_date'),
            'estimated_receiving_date'  => Arr::get($data, 'estimated_receiving_date'),
            'incoterm'                  => Arr::get($data, 'incoterm'),
            'port_of_export'            => Arr::get($data, 'port_of_export'),
            'port_of_import'            => Arr::get($data, 'port_of_import'),
            'delivery_address'          => Arr::get($data, 'delivery_address'),
        ];
    }
}
