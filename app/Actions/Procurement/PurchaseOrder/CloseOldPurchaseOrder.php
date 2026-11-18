<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-18 15:30
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Procurement\PurchaseOrder;

use App\Actions\GoodsIn\StockDelivery\UpdatePurchaseOrdersDeliveryStateFromStockDelivery;
use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrder\Hydrators\PurchaseOrderHydrateTransactions;
use App\Actions\Procurement\PurchaseOrder\Traits\HasPurchaseOrderHydrators;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Events\BroadcastAgentIncomingPurchaseOrdersChanged;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Procurement\AgentGoodsReceiptItem;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * Closes an old agent order whose goods were delivered before it was in aiku, so it leaves the agent's open lists
 * (submitted, agreed and every stage after) instead of waiting there for stages nobody will record. The order and its
 * open lines are settled, Counted & Costed reads as reached (stages before it "Not recorded") and the order is kept out
 * of the CHS score. A received stock delivery that carried the goods can be linked. Undo with UndoCloseOldPurchaseOrder.
 */
class CloseOldPurchaseOrder extends OrgAction
{
    use HasPurchaseOrderHydrators;

    private const array RECEIVED_STATES = [
        StockDeliveryStateEnum::RECEIVED,
        StockDeliveryStateEnum::CHECKED,
        StockDeliveryStateEnum::BOOKING_IN,
        StockDeliveryStateEnum::BOOKED_IN,
        StockDeliveryStateEnum::PLACED,
    ];

    /**
     * Dates of the agent's stages 2 to 6 (Agreement to Clean Hand Over). An order with any of them recorded is
     * followed through its stages in aiku, so it is not an old one. confirmed_at is not one: imported orders carry it.
     */
    public const array AGENT_STAGE_FIELDS = [
        'deposit_paid_at',
        'approved_ready_at',
        'sample_approved_at',
        'production_started_at',
        'produced_at',
        'qc_passed_at',
        'balance_paid_at',
        'compliance_complete_at',
        'handed_over_at',
    ];

    private PurchaseOrder $purchaseOrder;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return self::userIsAgent($request->user(), $this->purchaseOrder);
    }

    /**
     * Anyone who edits procurement in the order's agent organisation, as the PO page decides who sees the button,
     * also when they edit the buyer's organisation too.
     */
    public static function userIsAgent(User $user, PurchaseOrder $purchaseOrder): bool
    {
        $agentOrganisationId = $purchaseOrder->isAgentOrder() ? $purchaseOrder->agent?->organisation_id : null;

        return $agentOrganisationId !== null && $user->authTo("procurement.$agentOrganisationId.edit");
    }

    public function handle(PurchaseOrder $purchaseOrder, array $modelData): PurchaseOrder
    {
        $stockDelivery = Arr::get($modelData, 'stock_delivery_id') ? StockDelivery::find($modelData['stock_delivery_id']) : null;

        DB::transaction(function () use ($purchaseOrder, $modelData, $stockDelivery) {
            $stateBefore = $purchaseOrder->state;

            if ($stockDelivery) {
                $stockDelivery->purchaseOrders()->syncWithoutDetaching([$purchaseOrder->id]);
                $stockDelivery->update(['number_purchase_orders' => $stockDelivery->purchaseOrders()->count()]);
            }

            $openTransactions = $purchaseOrder->purchaseOrderTransactions()
                ->whereIn('state', [PurchaseOrderTransactionStateEnum::SUBMITTED, PurchaseOrderTransactionStateEnum::CONFIRMED]);
            $settledTransactionIds = (clone $openTransactions)->pluck('id')->all();
            $openTransactions->update(['state' => PurchaseOrderTransactionStateEnum::SETTLED]);

            $data = $purchaseOrder->data ?? [];
            Arr::set($data, 'closed_as_old', [
                'reason'                      => $modelData['reason'],
                'stock_delivery_id'           => $stockDelivery?->id,
                'user_id'                     => request()->user()?->id,
                'at'                          => now()->toIso8601String(),
                'state_before'                => $stateBefore->value,
                'settled_transaction_ids'     => $settledTransactionIds,
                'chs_excluded_before'         => (bool) $purchaseOrder->chs_excluded,
                'chs_exclusion_reason_before' => $purchaseOrder->chs_exclusion_reason,
            ]);

            $purchaseOrder->update([
                'data'                 => $data,
                'state'                => PurchaseOrderStateEnum::SETTLED,
                'settled_at'           => now(),
                'chs_excluded'         => true,
                'chs_exclusion_reason' => __('Old order closed: delivered before it was in aiku'),
            ]);

            if ($stockDelivery) {
                UpdatePurchaseOrdersDeliveryStateFromStockDelivery::run($stockDelivery->refresh());
            }
        });

        $purchaseOrder->refresh();
        PurchaseOrderHydrateTransactions::dispatch($purchaseOrder);
        $this->purchaseOrderHydrate($purchaseOrder);

        MarkPurchaseOrderStage::auditOverride($purchaseOrder, 'closed_as_old', $purchaseOrder->closedAsOld()['state_before'], PurchaseOrderStateEnum::SETTLED->value, $modelData['reason']);
        BroadcastAgentIncomingPurchaseOrdersChanged::dispatchFor($purchaseOrder);

        return $purchaseOrder;
    }

    /**
     * Why the order can not be closed, null when it can: only an open agent order imported into aiku (from Aurora, or
     * split from an order started before agent orders were followed in aiku) with no agent stage recorded, on no live
     * stock delivery still on its way, as one there settles through goods-in.
     */
    public static function blockedReason(PurchaseOrder $purchaseOrder): ?string
    {
        if (!$purchaseOrder->isAgentOrder()) {
            return __('Only an order through an agent');
        }

        if (!in_array($purchaseOrder->state, PurchaseOrderStateEnum::open())) {
            return __('Only an open order');
        }

        if (!self::isImported($purchaseOrder)) {
            return __('Only an old order imported into aiku, started before agent orders were followed here');
        }

        if (self::hasAgentStageRecorded($purchaseOrder)) {
            return __('Only an order with no agent stage recorded: this one is followed through its stages');
        }

        $onDeliveryOnItsWay = $purchaseOrder->stockDeliveries()
            ->whereNotIn('stock_deliveries.state', [StockDeliveryStateEnum::CANCELLED, StockDeliveryStateEnum::NOT_RECEIVED, ...self::RECEIVED_STATES])
            ->exists();
        if ($onDeliveryOnItsWay) {
            return __('The order is on a stock delivery that is not received yet');
        }

        return null;
    }

    public static function isImported(PurchaseOrder $purchaseOrder): bool
    {
        if ($purchaseOrder->source_id !== null) {
            return true;
        }

        $startedAt = $purchaseOrder->startedAt();

        return $startedAt !== null && $startedAt->lt(PurchaseOrder::AGENT_TRACKING_STARTED_AT);
    }

    public static function hasAgentStageRecorded(PurchaseOrder $purchaseOrder): bool
    {
        foreach (self::AGENT_STAGE_FIELDS as $field) {
            if ($purchaseOrder->{$field} !== null) {
                return true;
            }
        }

        if (!empty(Arr::get($purchaseOrder->data, 'stage_skips'))) {
            return true;
        }

        return AgentGoodsReceiptItem::where('purchase_order_id', $purchaseOrder->id)->exists();
    }

    /**
     * Received stock deliveries of the order's organisation, not linked to it yet, carrying at least one of its SKOs
     * on lines brought in without an order line (Aurora), newest first, with how many of their items are SKOs of the order.
     *
     * @return Builder<StockDelivery>
     */
    public static function linkableStockDeliveries(PurchaseOrder $purchaseOrder): Builder
    {
        $orgStockIds      = $purchaseOrder->purchaseOrderTransactions()->whereNotNull('org_stock_id')->distinct()->pluck('org_stock_id');
        $carriesOrderSkos = fn ($items) => $items->whereIn('stock_delivery_items.org_stock_id', $orgStockIds)
            ->whereRaw("stock_delivery_items.data->>'purchase_order_transaction_id' is null");

        return StockDelivery::query()
            ->where('organisation_id', $purchaseOrder->organisation_id)
            ->whereIn('state', self::RECEIVED_STATES)
            ->whereDoesntHave('purchaseOrders', fn (Builder $orders) => $orders->where('purchase_orders.id', $purchaseOrder->id))
            ->whereHas('items', $carriesOrderSkos)
            ->withCount(['items as number_matching_items' => $carriesOrderSkos])
            ->orderByDesc('id')
            ->limit(50);
    }

    public function rules(): array
    {
        return [
            'stock_delivery_id' => ['sometimes', 'nullable', 'integer', Rule::in(self::linkableStockDeliveries($this->purchaseOrder)->pluck('id')->all())],
            'reason'            => ['required', 'string', 'max:500'],
        ];
    }

    public function getValidationMessages(): array
    {
        return [
            'stock_delivery_id.in' => __('This stock delivery is not received, does not carry any SKO of this order, or is already linked'),
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        if ($blocked = self::blockedReason($this->purchaseOrder)) {
            $validator->errors()->add('reason', $blocked);
        }
    }

    public function action(PurchaseOrder $purchaseOrder, array $modelData): PurchaseOrder
    {
        $this->asAction      = true;
        $this->purchaseOrder = $purchaseOrder;
        $this->initialisation($purchaseOrder->organisation, $modelData);

        return $this->handle($purchaseOrder, $this->validatedData);
    }

    public function asController(PurchaseOrder $purchaseOrder, ActionRequest $request): PurchaseOrder
    {
        $this->purchaseOrder = $purchaseOrder;
        $this->initialisation($purchaseOrder->organisation, $request);

        $purchaseOrder = $this->handle($purchaseOrder, $this->validatedData);

        NotifyBuyerOfAgentStageChange::run($purchaseOrder, $request->user(), fn () => __('closed as an old order, delivered before it was in aiku: :reason', [
            'reason' => $purchaseOrder->closedAsOld()['reason'],
        ]));

        return $purchaseOrder;
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
