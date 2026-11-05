<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-05 11:44
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Procurement\PurchaseOrder;

use App\Actions\OrgAction;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SysAdmin\Group;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class DeleteEmptyPurchaseOrders extends OrgAction
{
    /**
     * An order not yet received can go when it is worth nothing (no lines, or every line cancelled or priced at zero,
     * and no extra costs), nothing was paid or asked as a deposit on it, and no goods, delivery or agent payment hangs
     * off it. Written against the alias po.
     */
    public static function deletableCondition(): string
    {
        return "po.state in ('in_process', 'submitted', 'confirmed')
            and ".self::withoutDepositCondition()."
            and coalesce(po.cost_extra, 0) + coalesce(po.cost_shipping, 0) + coalesce(po.cost_duties, 0) + coalesce(po.cost_tax, 0) = 0
            and not exists (
                select 1 from purchase_order_transactions
                where purchase_order_transactions.purchase_order_id = po.id
                    and purchase_order_transactions.deleted_at is null
                    and purchase_order_transactions.state <> 'cancelled'
                    and purchase_order_transactions.net_amount <> 0
            )
            and not exists (select 1 from agent_goods_receipt_items where agent_goods_receipt_items.purchase_order_id = po.id)
            and not exists (select 1 from agent_warehouse_stocks where agent_warehouse_stocks.purchase_order_id = po.id)
            and not exists (
                select 1 from purchase_order_stock_delivery
                join stock_deliveries on stock_deliveries.id = purchase_order_stock_delivery.stock_delivery_id
                where purchase_order_stock_delivery.purchase_order_id = po.id
                    and stock_deliveries.deleted_at is null
                    and stock_deliveries.state not in ('cancelled', 'not_received')
            )";
    }

    /**
     * No deposit was paid, planned, requested or held for the order, wherever it is recorded: on the order itself,
     * its deposit payments (undone ones excepted), the agent's deposits on it, directly or through the legacy agent
     * supplier order, deposit requests and agent payments. Written against the alias po.
     */
    public static function withoutDepositCondition(): string
    {
        return "po.deposit_paid_at is null
            and coalesce(po.deposit_amount, 0) = 0
            and not exists (
                select 1 from purchase_order_deposit_payments
                where purchase_order_deposit_payments.purchase_order_id = po.id
                    and purchase_order_deposit_payments.deleted_at is null
            )
            and not exists (
                select 1 from aspo_deposits
                left join agent_supplier_purchase_orders on agent_supplier_purchase_orders.id = aspo_deposits.agent_supplier_purchase_order_id
                where (aspo_deposits.purchase_order_id = po.id or agent_supplier_purchase_orders.purchase_order_id = po.id)
                    and aspo_deposits.state <> 'cancelled'
            )
            and not exists (
                select 1 from agent_supplier_purchase_orders
                where agent_supplier_purchase_orders.purchase_order_id = po.id
                    and (agent_supplier_purchase_orders.deposit_paid_at is not null or coalesce(agent_supplier_purchase_orders.deposit_amount, 0) <> 0)
            )
            and not exists (select 1 from deposit_request_items where deposit_request_items.purchase_order_id = po.id)
            and not exists (select 1 from agent_payments where agent_payments.purchase_order_id = po.id)";
    }

    public static function hasDeposit(PurchaseOrder $purchaseOrder): bool
    {
        return !DB::table('purchase_orders as po')
            ->where('po.id', $purchaseOrder->id)
            ->whereRaw(self::withoutDepositCondition())
            ->exists();
    }

    /**
     * @param  array<int, int>  $purchaseOrderIds
     *
     * @return array<int, int>
     */
    public static function deletableIds(Group $group, array $purchaseOrderIds): array
    {
        if (!$purchaseOrderIds) {
            return [];
        }

        return DB::table('purchase_orders as po')
            ->where('po.group_id', $group->id)
            ->whereNull('po.deleted_at')
            ->whereIn('po.id', $purchaseOrderIds)
            ->whereRaw(self::deletableCondition())
            ->pluck('po.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo('supply-chain.edit');
    }

    public function rules(): array
    {
        return [
            'purchase_orders'   => ['required', 'array', 'min:1', 'max:200'],
            'purchase_orders.*' => ['required', 'integer', 'distinct'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $requestedIds = array_map('intval', $this->get('purchase_orders'));
        $blockedIds   = array_diff($requestedIds, self::deletableIds($this->group, $requestedIds));

        if (!$blockedIds) {
            return;
        }

        $references = PurchaseOrder::where('group_id', $this->group->id)->whereIn('id', $blockedIds)->pluck('reference');

        $validator->errors()->add(
            'purchase_orders',
            $references->isEmpty()
                ? __('Some of these purchase orders no longer exist')
                : __('These purchase orders are no longer empty, or have a deposit, payment or delivery, and were not deleted: :references', ['references' => $references->implode(', ')])
        );
    }

    /**
     * @param  array<int, int>  $purchaseOrderIds
     */
    public function handle(Group $group, array $purchaseOrderIds): int
    {
        $deleted = 0;

        foreach ($purchaseOrderIds as $purchaseOrderId) {
            $deleted += DB::transaction(function () use ($group, $purchaseOrderId) {
                $purchaseOrder = PurchaseOrder::where('group_id', $group->id)->whereKey($purchaseOrderId)->lockForUpdate()->first();

                if (!$purchaseOrder || !self::deletableIds($group, [$purchaseOrder->id])) {
                    return 0;
                }

                return DeletePurchaseOrder::make()->handle($purchaseOrder) ? 1 : 0;
            });
        }

        return $deleted;
    }

    public function asController(ActionRequest $request): int
    {
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle($this->group, array_map('intval', $this->validatedData['purchase_orders']));
    }

    /**
     * @param  array<int, int>  $purchaseOrderIds
     */
    public function action(Group $group, array $purchaseOrderIds): int
    {
        $this->asAction = true;
        $this->initialisationFromGroup($group, ['purchase_orders' => $purchaseOrderIds]);

        return $this->handle($group, array_map('intval', $this->validatedData['purchase_orders']));
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
