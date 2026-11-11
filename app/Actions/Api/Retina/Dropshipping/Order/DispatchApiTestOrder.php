<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Nov 2026 16:40:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Api\Retina\Dropshipping\Order;

use App\Actions\Api\Retina\Dropshipping\Resource\OrderApiResource;
use App\Actions\Dispatching\DeliveryNote\DispatchTestOrder;
use App\Actions\RetinaApiAction;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

/**
 * Staging only (HELP-4645): the customer testing the API dispatches their own paid test order
 * with a TEST- tracking number, instead of asking us in the chat. Production answers 404.
 * The warehouse steps run as the staff command does, with no request user: the staff guards in
 * StoreShipment would otherwise read the customer's sales channel as the user.
 */
class DispatchApiTestOrder extends RetinaApiAction
{
    use AsAction;

    public function handle(Order $order): Order|JsonResponse
    {
        if ($order->customer_id != $this->customer->id || $order->customer_sales_channel_id != $this->customerSalesChannel->id) {
            return response()->json(['message' => 'Unable to make modifications for this order'], 403);
        }

        $user = User::where('group_id', $order->group_id)->where('status', true)->orderBy('id')->first();
        if (!$user) {
            return response()->json(['message' => 'No staff user to record the warehouse steps'], 422);
        }

        $lock = Cache::lock('dispatch_api_test_order:'.$order->id, 120);
        if (!$lock->get()) {
            return response()->json(['message' => 'This order is already being dispatched'], 409);
        }

        $userResolver = request()->getUserResolver();
        request()->setUserResolver(fn () => null);

        try {
            return DispatchTestOrder::run($order, $user, 'TEST-'.$order->reference);
        } catch (RuntimeException $e) {
            if (get_class($e) !== RuntimeException::class) {
                throw $e;
            }

            return response()->json(['message' => $e->getMessage()], 422);
        } finally {
            request()->setUserResolver($userResolver);
            $lock->release();
        }
    }

    public function jsonResponse(JsonResponse|Order $result): JsonResource|JsonResponse
    {
        if ($result instanceof JsonResponse) {
            return $result;
        }

        return OrderApiResource::make($result)->additional([
            'message' => 'Test order dispatched with a test tracking number. Nothing was shipped.',
        ]);
    }

    public function asController(Order $order, ActionRequest $request): Order|JsonResponse
    {
        abort_if(app()->isProduction(), 404);
        $this->initialisationFromDropshipping($request);

        return $this->handle($order);
    }
}
