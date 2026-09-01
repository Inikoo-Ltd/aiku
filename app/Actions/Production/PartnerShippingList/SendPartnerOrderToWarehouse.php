<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 31 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\PartnerShippingList;

use App\Actions\Ordering\Order\UpdateState\SendOrderToWarehouse;
use App\Actions\Procurement\PartnerShoppingListItem\StorePartnerStockDeliveryFromDeliveryNote;
use App\Actions\Ordering\Order\UpdateState\SubmitOrder;
use App\Actions\OrgAction;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Ordering\Order;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class SendPartnerOrderToWarehouse extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("procurement.{$this->organisation->id}.edit");
    }

    /**
     * @throws \Throwable
     */
    public function handle(Order $order): ?StockDelivery
    {
        if ($order->salesChannel?->code !== 'intercompany') {
            throw ValidationException::withMessages(['order' => __('Not an intercompany order')]);
        }
        if ($order->state !== OrderStateEnum::CREATING) {
            throw ValidationException::withMessages(['order' => __('Order has already been sent to the warehouse')]);
        }
        if (!$order->transactions()->exists()) {
            throw ValidationException::withMessages(['order' => __('Order has no items')]);
        }

        SubmitOrder::make()->action($order);
        $order->refresh();

        $deliveryNote = SendOrderToWarehouse::make()->action($order, [], releaseFromGate: true);

        if (!$deliveryNote) {
            return null;
        }

        return StorePartnerStockDeliveryFromDeliveryNote::run($deliveryNote);
    }

    /**
     * @throws \Throwable
     */
    public function asController(Organisation $organisation, Production $production, Order $order, ActionRequest $request): ?StockDelivery
    {
        $this->initialisation($organisation, $request);

        return $this->handle($order);
    }

    /**
     * @throws \Throwable
     */
    public function action(Order $order): ?StockDelivery
    {
        $this->asAction = true;
        $this->initialisation($order->organisation, []);

        return $this->handle($order);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
