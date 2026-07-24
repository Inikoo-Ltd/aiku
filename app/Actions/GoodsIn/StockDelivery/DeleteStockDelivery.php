<?php

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrder\Hydrators\PurchaseOrderHydrateTransactions;
use App\Actions\Procurement\PurchaseOrder\Traits\HasPurchaseOrderHydrators;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionDeliveryStateEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Procurement\PurchaseOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteStockDelivery extends OrgAction
{
    use AsAction;
    use HasPurchaseOrderHydrators;

    private StockDelivery $stockDelivery;

    private const DELETABLE_STATES = [
        StockDeliveryStateEnum::IN_PROCESS,
        StockDeliveryStateEnum::CONFIRMED,
        StockDeliveryStateEnum::READY_TO_SHIP,
    ];

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("procurement.{$this->organisation->id}.edit");
    }

    public function afterValidator(Validator $validator): void
    {
        if (!in_array($this->stockDelivery->state, self::DELETABLE_STATES, true)) {
            $validator->errors()->add('state', __('You can not delete this stock delivery with state :state', ['state' => $this->stockDelivery->state->value]));
        }
    }

    public function handle(StockDelivery $stockDelivery): ?PurchaseOrder
    {
        $purchaseOrders        = $stockDelivery->purchaseOrders()->get();
        $redirectPurchaseOrder = $purchaseOrders->first();

        $stockDelivery->items()->delete();
        $stockDelivery->purchaseOrders()->detach();
        $stockDelivery->delete();

        foreach ($purchaseOrders as $purchaseOrder) {
            $this->revertPurchaseOrder($purchaseOrder);
        }

        return $redirectPurchaseOrder;
    }

    private function revertPurchaseOrder(PurchaseOrder $purchaseOrder): void
    {
        $purchaseOrder->purchaseOrderTransactions()
            ->where('delivery_state', '!=', PurchaseOrderTransactionDeliveryStateEnum::IN_PROCESS)
            ->update(['delivery_state' => PurchaseOrderTransactionDeliveryStateEnum::IN_PROCESS]);

        $purchaseOrder->update([
            'delivery_state' => PurchaseOrderDeliveryStateEnum::IN_PROCESS,
        ]);

        PurchaseOrderHydrateTransactions::dispatch($purchaseOrder);
        $this->purchaseOrderHydrate($purchaseOrder);
    }

    public function asController(StockDelivery $stockDelivery, ActionRequest $request): ?PurchaseOrder
    {
        $this->stockDelivery = $stockDelivery;
        $this->initialisation($stockDelivery->organisation, $request);

        return $this->handle($stockDelivery);
    }

    public function action(StockDelivery $stockDelivery): ?PurchaseOrder
    {
        $this->asAction      = true;
        $this->stockDelivery = $stockDelivery;
        $this->initialisation($stockDelivery->organisation, []);

        return $this->handle($stockDelivery);
    }

    public function htmlResponse(?PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if (!$purchaseOrder) {
            return redirect()->back();
        }

        return redirect()->route('grp.org.procurement.purchase_orders.show', [
            'organisation'  => $purchaseOrder->organisation->slug,
            'purchaseOrder' => $purchaseOrder->slug,
        ]);
    }
}
