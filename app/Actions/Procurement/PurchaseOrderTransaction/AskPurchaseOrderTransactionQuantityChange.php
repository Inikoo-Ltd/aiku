<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 23 Oct 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrderTransaction;

use App\Actions\GoodsIn\StockDelivery\StoreStockDeliveryFromPurchaseOrder;
use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrder\NotifyBuyerOfAgentStageChange;
use App\Actions\Procurement\PurchaseOrder\UI\GetPurchaseOrderStages;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionQuantityQueryStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use App\Models\SysAdmin\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * The agent asks the buying organisation to change the quantity ordered on a line, before or after confirming the
 * order, as long as none of the line is shipped yet.
 */
class AskPurchaseOrderTransactionQuantityChange extends OrgAction
{
    use WithActionUpdate;

    private PurchaseOrderTransaction $purchaseOrderTransaction;

    public static function isQuantityChangeable(PurchaseOrderTransaction $purchaseOrderTransaction): bool
    {
        return in_array($purchaseOrderTransaction->purchaseOrder->state, [PurchaseOrderStateEnum::SUBMITTED, PurchaseOrderStateEnum::CONFIRMED])
            && in_array($purchaseOrderTransaction->state, [PurchaseOrderTransactionStateEnum::SUBMITTED, PurchaseOrderTransactionStateEnum::CONFIRMED])
            && !self::isShipped($purchaseOrderTransaction);
    }

    /**
     * Shipped once any of its units is in a container dispatched or further along, as the Shipped stage reads it.
     */
    public static function isShipped(PurchaseOrderTransaction $purchaseOrderTransaction): bool
    {
        return PurchaseOrderTransaction::query()
            ->whereKey($purchaseOrderTransaction->id)
            ->whereExists(fn (Builder $items) => StoreStockDeliveryFromPurchaseOrder::liveDeliveryItemsOfTransaction($items)
                ->join('stock_deliveries', 'stock_deliveries.id', 'stock_delivery_items.stock_delivery_id')
                ->whereIn('stock_deliveries.state', GetPurchaseOrderStages::SHIPPED_STATES))
            ->exists();
    }

    /**
     * Units of the line already put in a container not shipped yet: the quantity can not go below them.
     */
    public static function unitsInContainers(PurchaseOrderTransaction $purchaseOrderTransaction): float
    {
        return (float) PurchaseOrderTransaction::query()
            ->whereKey($purchaseOrderTransaction->id)
            ->selectSub(StoreStockDeliveryFromPurchaseOrder::quantityOnDelivery(), 'units_in_containers')
            ->value('units_in_containers');
    }

    public static function addQuantityErrors(Validator $validator, PurchaseOrderTransaction $purchaseOrderTransaction, float $quantity, string $key): void
    {
        if (!self::isQuantityChangeable($purchaseOrderTransaction)) {
            $validator->errors()->add($key, __('The quantity can only be changed until the line is shipped'));

            return;
        }

        $unitsInContainers = self::unitsInContainers($purchaseOrderTransaction);
        if ($quantity < $unitsInContainers) {
            $validator->errors()->add($key, __(':units units of this line are already in a container, take them out first to order fewer', [
                'units' => trimDecimalZeros($unitsInContainers),
            ]));
        }
    }

    public function handle(PurchaseOrderTransaction $purchaseOrderTransaction, array $modelData, ?User $user = null): PurchaseOrderTransaction
    {
        return $this->update($purchaseOrderTransaction, [
            'quantity_query_state'             => PurchaseOrderTransactionQuantityQueryStateEnum::PENDING,
            'quantity_query_quantity'          => round((float) $modelData['quantity_ordered'], 3),
            'quantity_query_previous_quantity' => $purchaseOrderTransaction->quantity_ordered,
            'quantity_query_note'              => $modelData['note'] ?? null,
            'quantity_queried_by_id'           => $user?->id,
            'quantity_queried_at'              => now(),
            'quantity_query_answered_at'       => null,
        ]);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        $purchaseOrder       = $this->purchaseOrderTransaction->purchaseOrder;
        $agentOrganisationId = $purchaseOrder->isAgentOrder() ? $purchaseOrder->agent?->organisation_id : null;

        return $agentOrganisationId !== null && $request->user()->authTo("procurement.$agentOrganisationId.edit");
    }

    public function afterValidator(Validator $validator): void
    {
        if ($validator->errors()->has('quantity_ordered')) {
            return;
        }

        self::addQuantityErrors($validator, $this->purchaseOrderTransaction, (float) $this->get('quantity_ordered'), 'quantity_ordered');
    }

    public function rules(): array
    {
        return [
            'quantity_ordered' => ['required', 'numeric', 'gt:0'],
            'note'             => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    public function action(PurchaseOrderTransaction $purchaseOrderTransaction, array $modelData, ?User $user = null): PurchaseOrderTransaction
    {
        $this->asAction                 = true;
        $this->purchaseOrderTransaction = $purchaseOrderTransaction;
        $this->initialisation($purchaseOrderTransaction->organisation, $modelData);

        return $this->handle($purchaseOrderTransaction, $this->validatedData, $user);
    }

    public function asController(PurchaseOrder $purchaseOrder, PurchaseOrderTransaction $purchaseOrderTransaction, ActionRequest $request): RedirectResponse
    {
        abort_unless($purchaseOrderTransaction->purchase_order_id === $purchaseOrder->id, 404);
        $this->purchaseOrderTransaction = $purchaseOrderTransaction;
        $this->initialisation($purchaseOrderTransaction->organisation, $request);

        $purchaseOrderTransaction = $this->handle($purchaseOrderTransaction, $this->validatedData, $request->user());

        NotifyBuyerOfAgentStageChange::run($purchaseOrder, $request->user(), __('the agent asks to order :new units of :code, was :old', [
            'code' => $purchaseOrderTransaction->supplierProduct?->code ?? $purchaseOrderTransaction->orgStock?->code,
            'new'  => trimDecimalZeros($purchaseOrderTransaction->quantity_query_quantity),
            'old'  => trimDecimalZeros($purchaseOrderTransaction->quantity_query_previous_quantity),
        ]));

        return back();
    }
}
