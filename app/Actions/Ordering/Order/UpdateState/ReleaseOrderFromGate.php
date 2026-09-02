<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 30 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order\UpdateState;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Ordering\WithOrderingEditAuthorisation;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\FulfilmentGateRelease;
use App\Models\Ordering\Order;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

class ReleaseOrderFromGate extends OrgAction
{
    use WithOrderingEditAuthorisation;

    /**
     * @throws \Throwable
     */
    public function handle(Order $order, ?int $releasedByUserId = null): ?DeliveryNote
    {
        if (!$order->at_gate_at) {
            throw ValidationException::withMessages(['order' => __('Order is not at the gate')]);
        }

        if ($order->state == OrderStateEnum::CREATING) {
            SubmitOrder::make()->action($order);
            $order->refresh();
        }

        $lastRelease = FulfilmentGateRelease::where('organisation_id', $order->organisation_id)
            ->where('customer_id', $order->customer_id)
            ->latest('id')->first();

        $deliveryNote = SendOrderToWarehouse::make()->action($order, [], releaseFromGate: true);

        FulfilmentGateRelease::create([
            'group_id'                   => $order->group_id,
            'organisation_id'            => $order->organisation_id,
            'warehouse_id'               => $deliveryNote?->warehouse_id,
            'customer_id'                => $order->customer_id,
            'order_id'                   => $order->id,
            'delivery_note_id'           => $deliveryNote?->id,
            'net_amount'                 => $order->net_amount,
            'number_items'               => $order->transactions()->count(),
            'seconds_since_last_release' => $lastRelease ? (int) abs(now()->diffInSeconds($lastRelease->created_at)) : null,
            'released_by_user_id'        => $releasedByUserId,
        ]);

        return $deliveryNote;
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }

    /**
     * @throws \Throwable
     */
    public function action(Order $order): ?DeliveryNote
    {
        $this->asAction = true;
        $this->initialisationFromShop($order->shop, []);

        return $this->handle($order);
    }

    /**
     * @throws \Throwable
     */
    public function asController(Order $order, ActionRequest $request): ?DeliveryNote
    {
        $this->initialisationFromShop($order->shop, $request);

        return $this->handle($order, $request->user()?->id);
    }
}
