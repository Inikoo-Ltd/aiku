<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Nov 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrder;

use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrder\Hydrators\PurchaseOrderHydrateTransactions;
use App\Actions\Procurement\PurchaseOrder\Traits\HasPurchaseOrderHydrators;
use App\Actions\Traits\Authorisations\WithPurchaseOrderAgentEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Enums\SupplyChain\SupplierProduct\SupplierProductStateEnum;
use App\Http\Resources\Procurement\PurchaseOrderResource;
use App\Models\Helpers\Audit;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class UpdatePurchaseOrderStateFromCancelled extends OrgAction
{
    use WithPurchaseOrderAgentEditAuthorisation;
    use HasPurchaseOrderHydrators;
    use WithActionUpdate;

    public const array COST_FIELDS = ['cost_extra', 'cost_shipping', 'cost_duties', 'cost_tax'];
    public const string SOURCE_UNKNOWN = 'unknown';

    /**
     * Brings a cancelled order back to the state it had, with the lines that were live and the costs cancelling wiped.
     *
     * @param  array<string, float|int|string|null>  $costs  entered by the user, needed only where the cancellation did not keep them
     */
    public function handle(PurchaseOrder $purchaseOrder, ?string $reason = null, array $costs = []): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder, $reason, $costs) {
            $purchaseOrder = PurchaseOrder::whereKey($purchaseOrder->id)->lockForUpdate()->firstOrFail();
            $cancellation  = self::cancellation($purchaseOrder);

            if ($blockedReason = self::blockedReason($purchaseOrder, $cancellation)) {
                abort(422, $blockedReason);
            }

            $restoredCosts = [];
            $missing       = [];
            foreach (self::COST_FIELDS as $field) {
                $value = $cancellation['costs'][$field] ?? null;
                if ($value === null && isset($costs[$field]) && $costs[$field] !== '') {
                    $value = (float) $costs[$field];
                }
                if ($value === null) {
                    $missing[$field] = __('Enter what this order had before it was cancelled');
                }
                $restoredCosts[$field] = $value;
            }
            if ($missing) {
                throw ValidationException::withMessages($missing);
            }

            if ($cancellation['cost_gap'] !== null && round($restoredCosts['cost_extra'] + $restoredCosts['cost_tax'] - $cancellation['cost_gap'], 2) != 0.0) {
                throw ValidationException::withMessages([
                    'cost_tax' => __('Extra costs and tax must add up to :amount, what the order had before it was cancelled', ['amount' => number_format($cancellation['cost_gap'], 2)]),
                ]);
            }

            foreach (self::restorableTransactions($purchaseOrder, $cancellation) as $transaction) {
                $line = $cancellation['lines'][$transaction->id];
                if (array_key_exists('net_amount', $line)) {
                    $amounts = Arr::only($line, ['net_amount', 'grp_net_amount', 'org_net_amount']);
                } else {
                    $netAmount = round((float) $transaction->quantity_ordered * (float) $transaction->unit_cost, 2);
                    $amounts   = [
                        'net_amount'     => $netAmount,
                        'grp_net_amount' => round($netAmount * (float) ($transaction->grp_exchange ?? 1), 2),
                        'org_net_amount' => round($netAmount * (float) ($transaction->org_exchange ?? 1), 2),
                    ];
                }
                $transaction->update(array_merge($amounts, ['state' => $line['state']]));
            }

            $purchaseOrder->update(array_merge($restoredCosts, [
                'state'        => $cancellation['state'],
                'cancelled_at' => null,
                'data'         => Arr::except($purchaseOrder->data ?? [], 'cancellation'),
            ]));

            CalculatePurchaseOrderTotalAmounts::run($purchaseOrder);
            if ($cancellation['totals']) {
                $purchaseOrder->update($cancellation['totals']);
            }
            if ($purchaseOrder->depositPayments()->exists()) {
                SyncPurchaseOrderDepositPaid::run($purchaseOrder);
            }
            PurchaseOrderHydrateTransactions::dispatch($purchaseOrder)->afterCommit();

            MarkPurchaseOrderStage::auditOverride($purchaseOrder, 'state', PurchaseOrderStateEnum::CANCELLED->value, $cancellation['state'], $reason ?: __('Cancel undone'));

            $this->purchaseOrderHydrate($purchaseOrder);

            return $purchaseOrder;
        });
    }

    /**
     * What cancelling wiped. Orders cancelled before data.cancellation existed are read from their own cancel audit,
     * which holds only the fields that changed: a cost it lacks was already 0. Extra and tax were not audited, so they
     * are asked for, and must add up to what is left of the old total. Without such an audit nothing is known and
     * the source is unknown.
     *
     * @return array{state: string, costs: array<string, float|null>, lines: array<int, array<string, mixed>>, totals: array<string, float>, source: string, cost_total: float|null, cost_gap: float|null}
     */
    public static function cancellation(PurchaseOrder $purchaseOrder): array
    {
        $stored = Arr::get($purchaseOrder->data ?? [], 'cancellation');

        if (is_array($stored)) {
            return [
                'state'      => $stored['state'] ?? PurchaseOrderStateEnum::SUBMITTED->value,
                'costs'      => Arr::only($stored['costs'] ?? [], self::COST_FIELDS),
                'lines'      => collect($stored['transactions'] ?? [])->mapWithKeys(fn (array $line) => [(int) $line['id'] => $line])->all(),
                'totals'     => Arr::only($stored['totals'] ?? [], ['cost_items', 'cost_total']),
                'source'     => $stored['source'] ?? UpdatePurchaseOrderStateToCancelled::SOURCE_USER,
                'cost_total' => null,
                'cost_gap'   => null,
            ];
        }

        $unknown = [
            'state'      => PurchaseOrderStateEnum::SUBMITTED->value,
            'costs'      => array_fill_keys(self::COST_FIELDS, null),
            'lines'      => [],
            'totals'     => [],
            'source'     => self::SOURCE_UNKNOWN,
            'cost_total' => null,
            'cost_gap'   => null,
        ];

        $audit = Audit::where('auditable_type', 'PurchaseOrder')
            ->where('auditable_id', $purchaseOrder->id)
            ->where('new_values->state', PurchaseOrderStateEnum::CANCELLED->value)
            ->latest('id')
            ->first();
        $old = $audit?->old_values ?? [];

        if (!$audit || !in_array($old['state'] ?? null, PurchaseOrderStateEnum::openValues(), true)
            || ($purchaseOrder->created_at && $audit->created_at && $audit->created_at->lt($purchaseOrder->created_at))) {
            return $unknown;
        }

        $state     = $old['state'];
        $costItems = (float) ($old['cost_items'] ?? 0);
        $costTotal = (float) ($old['cost_total'] ?? 0);
        $costs     = [
            'cost_extra'    => array_key_exists('cost_extra', $old) ? (float) $old['cost_extra'] : null,
            'cost_shipping' => (float) ($old['cost_shipping'] ?? 0),
            'cost_duties'   => (float) ($old['cost_duties'] ?? 0),
            'cost_tax'      => array_key_exists('cost_tax', $old) ? (float) $old['cost_tax'] : null,
        ];

        $cancelledLines = $purchaseOrder->purchaseOrderTransactions()
            ->where('state', PurchaseOrderTransactionStateEnum::CANCELLED)
            ->get(['id', 'data', 'quantity_ordered', 'unit_cost']);
        $cancelledOneByOne = Audit::where('auditable_type', 'PurchaseOrderTransaction')
            ->whereIn('auditable_id', $cancelledLines->pluck('id'))
            ->where('new_values->state', PurchaseOrderTransactionStateEnum::CANCELLED->value)
            ->pluck('auditable_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $restorable = $cancelledLines->reject(fn (PurchaseOrderTransaction $transaction) => in_array($transaction->id, $cancelledOneByOne, true)
            || array_key_exists('cancellation_reason', $transaction->data ?? []));

        $source = UpdatePurchaseOrderStateToCancelled::SOURCE_USER;
        if ($restorable->isEmpty() && $cancelledLines->contains(fn (PurchaseOrderTransaction $transaction) => array_key_exists('moved_to', $transaction->data ?? []))) {
            $source = UpdatePurchaseOrderStateToCancelled::SOURCE_MOVED;
        } elseif ($costItems == 0.0) {
            $source = UpdatePurchaseOrderStateToCancelled::SOURCE_EMPTIED;
        }

        $rebuiltItems = round($restorable->sum(fn (PurchaseOrderTransaction $transaction) => round((float) $transaction->quantity_ordered * (float) $transaction->unit_cost, 2)), 2);
        $costGap      = null;
        if (abs($rebuiltItems - $costItems) > 0.01) {
            $costs     = array_fill_keys(self::COST_FIELDS, null);
            $costTotal = null;
        } elseif ($costs['cost_extra'] === null || $costs['cost_tax'] === null) {
            $costGap = round($costTotal - $costItems - $costs['cost_shipping'] - $costs['cost_duties'] - ($costs['cost_extra'] ?? 0) - ($costs['cost_tax'] ?? 0), 2);
            if ($costGap == 0.0) {
                $costs['cost_extra'] ??= 0.0;
                $costs['cost_tax']   ??= 0.0;
                $costGap = null;
            }
        }

        return [
            'state'      => $state,
            'costs'      => $costs,
            'lines'      => $restorable->mapWithKeys(fn (PurchaseOrderTransaction $transaction) => [$transaction->id => ['id' => $transaction->id, 'state' => PurchaseOrderStateEnum::from($state)->transactionState()->value]])->all(),
            'totals'     => [],
            'source'     => $source,
            'cost_total' => $costTotal,
            'cost_gap'   => $costGap,
        ];
    }

    public static function blockedReason(PurchaseOrder $purchaseOrder, ?array $cancellation = null): ?string
    {
        if ($purchaseOrder->state !== PurchaseOrderStateEnum::CANCELLED) {
            return __('Only cancelled purchase orders can be brought back');
        }

        $cancellation ??= self::cancellation($purchaseOrder);

        if ($cancellation['source'] === self::SOURCE_UNKNOWN) {
            return __('This order was cancelled before aiku kept what cancelling removes, so it can not be brought back');
        }

        if ($cancellation['source'] === UpdatePurchaseOrderStateToCancelled::SOURCE_MOVED) {
            return __('This order was cancelled because its items were moved to another supplier');
        }

        if ($cancellation['source'] === UpdatePurchaseOrderStateToCancelled::SOURCE_EMPTIED) {
            return __('This order was cancelled because it was left with nothing to order');
        }

        $transactions = self::restorableTransactions($purchaseOrder, $cancellation);

        if ($transactions->isEmpty()) {
            return __('This order has no items to bring back');
        }

        if ($unpriced = $transactions->first(fn (PurchaseOrderTransaction $transaction) => !array_key_exists('net_amount', $cancellation['lines'][$transaction->id]) && (float) $transaction->unit_cost <= 0)) {
            return __('The unit cost of :code is not known', ['code' => $unpriced->supplierProduct?->code]);
        }

        $unavailable = $transactions->first(fn (PurchaseOrderTransaction $transaction) => $transaction->supplierProduct && (
            $transaction->supplierProduct->state === SupplierProductStateEnum::DISCONTINUED
            || !$transaction->supplierProduct->is_available
            || ($transaction->orgSupplierProduct && !$transaction->orgSupplierProduct->is_available)
        ));
        if ($unavailable) {
            return __(':code is discontinued or no longer available', ['code' => $unavailable->supplierProduct->code]);
        }

        if ($purchaseOrder->stockDeliveries()->where('stock_deliveries.state', '!=', StockDeliveryStateEnum::CANCELLED)->exists()) {
            return __('This order has a stock delivery that is not cancelled');
        }

        return null;
    }

    /**
     * Live orders made after the cancellation that already order the same supplier products.
     *
     * @return array<int, array{id: int, reference: string, state: string}>
     */
    public static function duplicateOrders(PurchaseOrder $purchaseOrder, ?array $cancellation = null): array
    {
        $cancellation     ??= self::cancellation($purchaseOrder);
        $supplierProductIds = self::restorableTransactions($purchaseOrder, $cancellation)->pluck('supplier_product_id')->filter()->unique()->values();

        if ($supplierProductIds->isEmpty()) {
            return [];
        }

        return PurchaseOrder::where('organisation_id', $purchaseOrder->organisation_id)
            ->whereKeyNot($purchaseOrder->id)
            ->whereIn('state', PurchaseOrderStateEnum::live())
            ->when($purchaseOrder->cancelled_at, fn ($query, $cancelledAt) => $query->where('created_at', '>=', $cancelledAt))
            ->whereHas('purchaseOrderTransactions', fn ($query) => $query->whereIn('supplier_product_id', $supplierProductIds)->where('state', '!=', PurchaseOrderTransactionStateEnum::CANCELLED))
            ->orderBy('id')
            ->get(['id', 'reference', 'state'])
            ->map(fn (PurchaseOrder $order) => ['id' => $order->id, 'reference' => $order->reference, 'state' => PurchaseOrderStateEnum::labels()[$order->state->value]])
            ->all();
    }

    /**
     * @return \Illuminate\Support\Collection<int, PurchaseOrderTransaction>
     */
    private static function restorableTransactions(PurchaseOrder $purchaseOrder, array $cancellation): \Illuminate\Support\Collection
    {
        if (!$cancellation['lines']) {
            return collect();
        }

        return $purchaseOrder->purchaseOrderTransactions()
            ->whereIn('id', array_keys($cancellation['lines']))
            ->where('state', PurchaseOrderTransactionStateEnum::CANCELLED)
            ->with(['supplierProduct', 'orgSupplierProduct'])
            ->get();
    }

    public function rules(): array
    {
        if ($this->asAction) {
            return [
                'reason' => ['sometimes', 'nullable', 'string', 'max:500'],
                ...array_fill_keys(self::COST_FIELDS, ['sometimes', 'nullable', 'numeric', 'min:0']),
            ];
        }

        return [
            'reason'                => ['required', 'string', 'max:500'],
            'counterparty_informed' => ['required', 'string', 'in:yes,Yes,YES'],
            ...array_fill_keys(self::COST_FIELDS, ['sometimes', 'nullable', 'numeric', 'min:0']),
        ];
    }

    public function getValidationMessages(): array
    {
        return [
            'counterparty_informed.*' => __('Type yes to confirm you have informed the supplier, agent or partner that this order is back on.'),
        ];
    }

    public function asController(PurchaseOrder $purchaseOrder, ActionRequest $request): PurchaseOrder
    {
        $this->initialisation($purchaseOrder->organisation, $request);

        return $this->handle($purchaseOrder, trim($this->validatedData['reason']), Arr::only($this->validatedData, self::COST_FIELDS));
    }

    /**
     * @param  array<string, mixed>  $modelData
     */
    public function action(PurchaseOrder $purchaseOrder, array $modelData = []): PurchaseOrder
    {
        $this->asAction = true;
        $this->initialisation($purchaseOrder->organisation, $modelData);

        return $this->handle($purchaseOrder, Arr::get($this->validatedData, 'reason'), Arr::only($this->validatedData, self::COST_FIELDS));
    }

    public function jsonResponse(PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        return new PurchaseOrderResource($purchaseOrder);
    }
}
