<?php

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\Procurement\WithProcurementSerialReferences;
use App\Actions\Traits\Authorisations\WithProcurementEditAuthorisation;
use App\Actions\GoodsIn\StockDelivery\Hydrators\StockDeliveriesHydrateItems;
use App\Actions\GoodsIn\StockDeliveryItem\StoreStockDeliveryItem;
use App\Actions\Procurement\PurchaseOrder\Hydrators\PurchaseOrderHydrateDeliveryState;
use App\Actions\Procurement\PurchaseOrder\Hydrators\PurchaseOrderHydrateTransactions;
use App\Actions\OrgAction;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Enums\Helpers\SerialReference\SerialReferenceModelEnum;
use App\Enums\Procurement\AgentGoodsReceipt\AgentGoodsReceiptStateEnum;
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
        if (!in_array($purchaseOrder->state, self::shippingStates(), true)) {
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

            $purchaseOrderTransactionsQuery = self::whereShippable($purchaseOrder->purchaseOrderTransactions())
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

            foreach ($purchaseOrderTransactions as $purchaseOrderTransaction) {
                $unitQuantity = (float) Arr::get($requestedUnitQuantities, $purchaseOrderTransaction->id, $purchaseOrderTransaction->quantity_ready_to_ship);
                $netAmount    = $unitQuantity == (float) $purchaseOrderTransaction->quantity_ordered
                    ? (float) $purchaseOrderTransaction->net_amount
                    : (float) $purchaseOrderTransaction->net_amount * $unitQuantity / (float) $purchaseOrderTransaction->quantity_ordered;

                $keptUnits = self::keptUnitsShipped($purchaseOrderTransaction, $unitQuantity);

                $itemInThisDelivery = $stockDelivery->items()
                    ->whereRaw("(data->>'purchase_order_transaction_id')::bigint = ?", [$purchaseOrderTransaction->id])
                    ->whereNotIn('state', [StockDeliveryItemStateEnum::CANCELLED, StockDeliveryItemStateEnum::NOT_RECEIVED])
                    ->first();

                if ($itemInThisDelivery) {
                    $this->addToDeliveryItem($itemInThisDelivery, $unitQuantity, $netAmount, $keptUnits);

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
                        'data'          => array_filter([
                            'purchase_order_transaction_id' => $purchaseOrderTransaction->id,
                            'kept_at_agent_units'           => $keptUnits ?: null,
                        ]),
                    ], $this->getExchanges($purchaseOrderTransaction))
                );
            }

            UpdateStockDeliveryStateFromItems::run($stockDelivery);

            PurchaseOrderHydrateDeliveryState::run($purchaseOrder);

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
     * Agreed orders ship; a settled one ships only the units it kept at the agent (whereShippable).
     *
     * @return array<int, PurchaseOrderStateEnum>
     */
    public static function shippingStates(): array
    {
        return [...PurchaseOrderStateEnum::agreed(), PurchaseOrderStateEnum::SETTLED];
    }

    public const string KEPT_AT_AGENT_SQL = 'coalesce(purchase_order_transactions.quantity_kept_at_agent, 0)';

    /**
     * A confirmed line is awaiting delivery while part of what was ordered is in no container yet and was not kept at
     * the agent (INI-525): an agent can ship a line across several containers.
     */
    public static function whereAwaitingDelivery(EloquentBuilder|HasMany $transactions): EloquentBuilder|HasMany
    {
        $quantityOnDelivery = self::quantityOnDelivery();

        return $transactions
            ->where('purchase_order_transactions.state', PurchaseOrderTransactionStateEnum::CONFIRMED)
            ->where(fn ($query) => $query
                ->whereNotExists(fn (Builder $items) => self::liveDeliveryItemsOfTransaction($items))
                ->orWhereRaw('purchase_order_transactions.quantity_ordered - ('.$quantityOnDelivery->toSql().') - '.self::KEPT_AT_AGENT_SQL.' > '.self::QUANTITY_TOLERANCE, $quantityOnDelivery->getBindings()));
    }

    /**
     * Lines with units in no container yet, which may still go in one: awaiting delivery, or kept at the agent
     * (INI-525), also once the line settled without them.
     */
    public static function whereShippable(EloquentBuilder|HasMany $transactions): EloquentBuilder|HasMany
    {
        $quantityOnDelivery = self::quantityOnDelivery();

        return $transactions->where(fn ($query) => $query
            ->where(fn ($confirmed) => $confirmed
                ->where('purchase_order_transactions.state', PurchaseOrderTransactionStateEnum::CONFIRMED)
                ->where(fn ($unshipped) => $unshipped
                    ->whereNotExists(fn (Builder $items) => self::liveDeliveryItemsOfTransaction($items))
                    ->orWhereRaw('purchase_order_transactions.quantity_ordered - ('.$quantityOnDelivery->toSql().') > '.self::QUANTITY_TOLERANCE, $quantityOnDelivery->getBindings())))
            ->orWhere(fn ($kept) => self::whereHoldsKeptAtAgent($kept)->where('purchase_order_transactions.state', PurchaseOrderTransactionStateEnum::SETTLED)));
    }

    /**
     * Lines whose units kept at the agent are not all in a container yet.
     */
    public static function whereHoldsKeptAtAgent(EloquentBuilder|HasMany|Builder $transactions): EloquentBuilder|HasMany|Builder
    {
        $held = self::quantityHeldAtAgentSql();

        return $transactions->whereRaw($held['sql'].' > '.self::QUANTITY_TOLERANCE, $held['bindings']);
    }

    /**
     * The units kept at the agent still not in a container: least(kept, ordered - on containers).
     *
     * @return array{sql: string, bindings: array<int, mixed>}
     */
    public static function quantityHeldAtAgentSql(): array
    {
        $quantityOnDelivery = self::quantityOnDelivery();

        return [
            'sql'      => 'least('.self::KEPT_AT_AGENT_SQL.', purchase_order_transactions.quantity_ordered - ('.$quantityOnDelivery->toSql().'))',
            'bindings' => $quantityOnDelivery->getBindings(),
        ];
    }

    /**
     * The units of a line on deliveries that arrived (received or later), leaving out the units kept at the agent that
     * shipped later (data.kept_at_agent_units), in a subquery correlated to purchase_order_transactions: the kept
     * quantity already counts them.
     */
    public static function quantityArrived(): Builder
    {
        return self::liveDeliveryItemsOfTransaction(DB::query())
            ->join('stock_deliveries as arrived_deliveries', 'arrived_deliveries.id', 'stock_delivery_items.stock_delivery_id')
            ->whereNotIn('arrived_deliveries.state', array_column(self::NOT_ARRIVED_STATES, 'value'))
            ->selectRaw("coalesce(sum(stock_delivery_items.unit_quantity - least(stock_delivery_items.unit_quantity, coalesce((stock_delivery_items.data->>'kept_at_agent_units')::numeric, 0))), 0)");
    }

    /**
     * How many of the units going in a container come out of what was kept at the agent: what goes beyond the units
     * still awaited. Needs quantity_awaiting_delivery (ordered - on containers) selected.
     */
    public static function keptUnitsShipped(PurchaseOrderTransaction $purchaseOrderTransaction, float $unitQuantity): float
    {
        $kept = (float) $purchaseOrderTransaction->quantity_kept_at_agent;
        if ($kept <= 0) {
            return 0;
        }

        $notOnContainers = max(0, (float) $purchaseOrderTransaction->quantity_awaiting_delivery);
        $keptUnits       = round(min($unitQuantity, $notOnContainers) - max(0, $notOnContainers - $kept), 3);

        return $keptUnits > self::QUANTITY_TOLERANCE ? $keptUnits : 0;
    }

    public const array NOT_ARRIVED_STATES = [
        StockDeliveryStateEnum::IN_PROCESS,
        StockDeliveryStateEnum::CONFIRMED,
        StockDeliveryStateEnum::READY_TO_SHIP,
        StockDeliveryStateEnum::DISPATCHED,
    ];

    /**
     * A line with units kept at the agent is all in once what arrived and what was kept cover what was ordered: units
     * kept and shipped later in another container do not hold it back.
     */
    public static function whereKeptLineAllArrived(EloquentBuilder|HasMany $transactions): EloquentBuilder|HasMany
    {
        $arrived = self::quantityArrived();

        return $transactions
            ->where('purchase_order_transactions.quantity_kept_at_agent', '>', 0)
            ->whereRaw('purchase_order_transactions.quantity_ordered - '.self::KEPT_AT_AGENT_SQL.' - ('.$arrived->toSql().') <= '.self::QUANTITY_TOLERANCE, $arrived->getBindings());
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
        return $purchaseOrder->isAgentOrder();
    }

    /**
     * Lines with units that can go in a shipment now: awaiting delivery and, from an agent warehouse, received there
     * and not in a shipment yet.
     */
    public static function whereReadyToShip(EloquentBuilder|HasMany $transactions, bool $fromAgentWarehouse): EloquentBuilder|HasMany
    {
        $transactions = self::whereShippable($transactions);
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
            return self::selectReadyToShipCappedForSettled($transactions, 'purchase_order_transactions.quantity_ordered - ('.$onDelivery->toSql().')', $onDelivery->getBindings());
        }

        $received = self::quantityReceivedAtAgentWarehouse();

        return self::selectReadyToShipCappedForSettled(
            $transactions,
            'least(purchase_order_transactions.quantity_ordered, ('.$received->toSql().')) - ('.$onDelivery->toSql().')',
            [...$received->getBindings(), ...$onDelivery->getBindings()]
        );
    }

    /**
     * A settled line ships only what it kept at the agent and is not in a container yet.
     */
    private static function selectReadyToShipCappedForSettled(EloquentBuilder|HasMany $transactions, string $readyToShipSql, array $bindings): EloquentBuilder|HasMany
    {
        $held = self::quantityHeldAtAgentSql();

        return $transactions->selectRaw(
            "case when purchase_order_transactions.state = '".PurchaseOrderTransactionStateEnum::SETTLED->value."' then least(".$readyToShipSql.', '.$held['sql'].') else '.$readyToShipSql.' end as quantity_ready_to_ship',
            [...$bindings, ...$held['bindings'], ...$bindings]
        );
    }

    /**
     * The units of a purchase order line the agent's goods receipts accepted, rejects left out, in a subquery
     * correlated to purchase_order_transactions. A line of an order created before the agent's
     * goods_receipts_required_from counts as received in full, receipts or not: its stock reached the agent warehouse
     * before goods receipts were recorded there, so a later receipt for part of it must not undo what already shipped
     * (INI-174, Agent Indo's sheet import).
     */
    public static function quantityReceivedAtAgentWarehouse(): Builder
    {
        return DB::table('agent_goods_receipt_items')
            ->join('agent_goods_receipts', 'agent_goods_receipts.id', 'agent_goods_receipt_items.agent_goods_receipt_id')
            ->where('agent_goods_receipts.state', AgentGoodsReceiptStateEnum::RECEIVED->value)
            ->whereColumn('agent_goods_receipt_items.purchase_order_transaction_id', 'purchase_order_transactions.id')
            ->selectRaw(
                "case when exists (
                    select 1 from purchase_orders
                    join agents on agents.id = purchase_orders.agent_id
                    where purchase_orders.id = purchase_order_transactions.purchase_order_id
                    and purchase_orders.created_at < (agents.settings->>'goods_receipts_required_from')::date
                ) then purchase_order_transactions.quantity_ordered
                else coalesce(sum(agent_goods_receipt_items.quantity - coalesce(agent_goods_receipt_items.quantity_rejected, 0)), 0) end"
            );
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
                        ->whereIn('state', [PurchaseOrderTransactionStateEnum::CONFIRMED->value, PurchaseOrderTransactionStateEnum::SETTLED->value])
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
     * join a delivery the agent already has in process, as long as both are in the same currency. An order straight
     * from a supplier joins that supplier's containers in process the same way (HELP-4725).
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
        $isDirectSupplierOrder = $purchaseOrder->parent_type === 'OrgSupplier' && $purchaseOrder->agent_id === null;
        $orgAgent              = $purchaseOrder->isAgentOrder() ? $purchaseOrder->orgAgentOfOrder() : null;

        return StockDelivery::query()
            ->when(
                $isDirectSupplierOrder,
                fn (EloquentBuilder $query) => $query->where('parent_type', 'OrgSupplier')->where('parent_id', $purchaseOrder->parent_id)->whereNull('agent_id')->whereNull('delivery_note_id'),
                fn (EloquentBuilder $query) => $query->when(!$orgAgent, fn (EloquentBuilder $query) => $query->whereRaw('false'))
                    ->where('parent_type', 'OrgAgent')
                    ->where('parent_id', $orgAgent?->id)
            )
            ->whereIn('state', StockDeliveryStateEnum::beforeDispatch())
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

    private function addToDeliveryItem(StockDeliveryItem $stockDeliveryItem, float $unitQuantity, float $netAmount, float $keptUnits = 0): void
    {
        $netAmount = (float) $stockDeliveryItem->net_amount + $netAmount;
        $data      = $stockDeliveryItem->data ?? [];
        if ($keptUnits > 0) {
            $data['kept_at_agent_units'] = round((float) Arr::get($data, 'kept_at_agent_units', 0) + $keptUnits, 3);
        }

        $stockDeliveryItem->update([
            'state'          => StockDeliveryItemStateEnum::IN_PROCESS,
            'data'           => $data,
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
