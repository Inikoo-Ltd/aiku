<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-13 08:40
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Procurement\PurchaseOrderTransaction;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithPurchaseOrderAgentEditAuthorisation;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;

/**
 * The open orders of the same agent and currency an order can pull items from, its own supplier's first, with what the
 * person pulling must be warned of: production started or a deposit paid, on them or on the order taking the items.
 */
class GetPurchaseOrderPullSources extends OrgAction
{
    use WithPurchaseOrderAgentEditAuthorisation;

    /**
     * @return array{into: array<string, mixed>, orders: array<int, array<string, mixed>>}
     */
    public function handle(PurchaseOrder $purchaseOrder, User $user): array
    {
        $orders = MovePurchaseOrderTransactionToSupplier::purchaseOrdersToPullFromQuery($purchaseOrder)
            ->withExists('depositPayments')
            ->orderByRaw('parent_id = ? desc', [$purchaseOrder->parent_id])
            ->orderBy('parent_code')
            ->orderByDesc('id')
            ->get();

        $liveTransactions   = PurchaseOrderTransaction::whereIn('purchase_order_id', $orders->pluck('id')->all())
            ->whereIn('state', [PurchaseOrderTransactionStateEnum::SUBMITTED, PurchaseOrderTransactionStateEnum::CONFIRMED])
            ->get();
        $pullable           = MovePurchaseOrderTransactionToSupplier::pullableQuantities($liveTransactions);
        $movableItemsCounts = $liveTransactions
            ->filter(fn (PurchaseOrderTransaction $transaction) => $pullable[$transaction->id]['quantity'] > 0)
            ->countBy('purchase_order_id');
        $liveItemsCounts    = $liveTransactions->countBy('purchase_order_id');

        return [
            'into'   => [
                'reference'              => $purchaseOrder->reference,
                'org_supplier_id'        => $purchaseOrder->parent_id,
                'supplier_code'          => $purchaseOrder->parent_code,
                'currency'               => $purchaseOrder->currency?->code,
                'production_started'     => $purchaseOrder->hasProductionStarted(),
                'deposit_paid'           => $purchaseOrder->depositPayments()->exists(),
                'accepts_supplier_price' => $user->authTo("procurement.$purchaseOrder->organisation_id.edit"),
            ],
            'orders' => $orders->map(fn (PurchaseOrder $sourcePurchaseOrder) => [
                'id'                 => $sourcePurchaseOrder->id,
                'reference'          => $sourcePurchaseOrder->reference,
                'slug'               => $sourcePurchaseOrder->slug,
                'url'                => route('grp.org.procurement.purchase_orders.show', [$purchaseOrder->organisation->slug, $sourcePurchaseOrder->slug]),
                'org_supplier_id'    => $sourcePurchaseOrder->parent_id,
                'supplier_code'      => $sourcePurchaseOrder->parent_code,
                'supplier_name'      => $sourcePurchaseOrder->parent_name,
                'state_label'        => PurchaseOrderStateEnum::labels()[$sourcePurchaseOrder->state->value],
                'number_of_items'    => (int) $liveItemsCounts->get($sourcePurchaseOrder->id, 0),
                'movable_items'      => (int) $movableItemsCounts->get($sourcePurchaseOrder->id, 0),
                'production_started' => $sourcePurchaseOrder->hasProductionStarted(),
                'deposit_paid'       => (bool) $sourcePurchaseOrder->deposit_payments_exists,
            ])->values()->all(),
        ];
    }

    public function asController(PurchaseOrder $purchaseOrder, ActionRequest $request): array
    {
        $this->initialisation($purchaseOrder->organisation, $request);

        abort_unless(MovePurchaseOrderTransactionToSupplier::canPullInto($purchaseOrder), 422, __(':reference can no longer take items', ['reference' => $purchaseOrder->reference]));

        return $this->handle($purchaseOrder, $request->user());
    }
}
