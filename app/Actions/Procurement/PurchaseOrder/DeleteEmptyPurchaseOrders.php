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
     * An order can go only while it is still in process, worth nothing (no lines, or every line cancelled or priced
     * at zero, and no extra costs) and nothing was paid or asked as a deposit on it. Written against the alias po.
     */
    public static function deletableCondition(): string
    {
        return "po.state = 'in_process'
            and po.deposit_paid_at is null
            and coalesce(po.cost_extra, 0) + coalesce(po.cost_shipping, 0) + coalesce(po.cost_duties, 0) + coalesce(po.cost_tax, 0) = 0
            and not exists (
                select 1 from purchase_order_transactions
                where purchase_order_transactions.purchase_order_id = po.id
                    and purchase_order_transactions.deleted_at is null
                    and purchase_order_transactions.state <> 'cancelled'
                    and purchase_order_transactions.net_amount <> 0
            )
            and not exists (
                select 1 from purchase_order_deposit_payments
                where purchase_order_deposit_payments.purchase_order_id = po.id
                    and purchase_order_deposit_payments.deleted_at is null
            )
            and not exists (
                select 1 from aspo_deposits
                where aspo_deposits.purchase_order_id = po.id
                    and aspo_deposits.state <> 'cancelled'
            )";
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
                : __('These purchase orders are no longer empty or in process, or have a deposit, and were not deleted: :references', ['references' => $references->implode(', ')])
        );
    }

    /**
     * @param  array<int, int>  $purchaseOrderIds
     */
    public function handle(Group $group, array $purchaseOrderIds): int
    {
        $purchaseOrders = PurchaseOrder::where('group_id', $group->id)
            ->whereIn('id', self::deletableIds($group, $purchaseOrderIds))
            ->get();

        foreach ($purchaseOrders as $purchaseOrder) {
            DB::transaction(fn () => DeletePurchaseOrder::make()->action($purchaseOrder));
        }

        return $purchaseOrders->count();
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
