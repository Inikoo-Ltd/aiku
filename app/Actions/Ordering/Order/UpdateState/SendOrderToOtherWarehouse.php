<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order\UpdateState;

use App\Actions\Dispatching\CrossDispatch\RaiseCrossDispatchDeliveryNote;
use App\Actions\Dispatching\CrossDispatch\RouteOrderToOtherWarehouse;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Ordering\WithOrderingEditAuthorisation;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class SendOrderToOtherWarehouse extends OrgAction
{
    use WithOrderingEditAuthorisation;

    /**
     * INI-485. Staff release an order held for another warehouse by sending it to one of the warehouses the shop counts
     * that can ship it whole after its margin. Only a held order: any other order is sent with INI-484's own button.
     * Do-not-pick and office export holds still apply, as on the way home; the resolver re-checks the order, and any
     * refusal comes back as a validation error on the order.
     *
     * @throws \Throwable
     */
    public function handle(Order $order, Organisation $dispatcher): DeliveryNote
    {
        return DB::transaction(function () use ($order, $dispatcher) {
            $lockedOrder = RouteOrderToOtherWarehouse::lockStillHeld($order);
            if (!$lockedOrder) {
                throw ValidationException::withMessages(['cross_dispatch' => __('This order is not held for another warehouse.')]);
            }

            $option = collect(RouteOrderToOtherWarehouse::make()->warehouseOptions($lockedOrder))->first(fn (array $option) => $option['organisation']->id === $dispatcher->id);
            if (!$option) {
                throw ValidationException::withMessages(['cross_dispatch' => __('This shop does not count the stock of :warehouse.', ['warehouse' => $dispatcher->name])]);
            }

            if ($officeHold = SendOrderToWarehouse::make()->officeHoldReason($lockedOrder)) {
                throw ValidationException::withMessages(['cross_dispatch' => __('Held: :reason', ['reason' => $officeHold])]);
            }

            if (!$option['can_ship']) {
                throw ValidationException::withMessages(['cross_dispatch' => __(':warehouse cannot ship it whole: :shortfall', ['warehouse' => $dispatcher->name, 'shortfall' => $option['shortfall']])]);
            }

            return RaiseCrossDispatchDeliveryNote::run($lockedOrder, $dispatcher);
        });
    }

    /**
     * @throws \Throwable
     */
    public function asController(Order $order, Organisation $organisation, ActionRequest $request): DeliveryNote
    {
        $this->initialisationFromShop($order->shop, $request);

        return $this->handle($order, $organisation);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
