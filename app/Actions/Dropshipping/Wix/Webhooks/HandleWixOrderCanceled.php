<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Wix\Webhooks;

use App\Actions\Ordering\Order\UpdateOrder;
use App\Actions\Ordering\Order\UpdateState\CancelOrder;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Dropshipping\WixUser;
use App\Models\Ordering\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class HandleWixOrderCanceled
{
    use AsAction;
    use WithWixOrderWebhook;

    public const string EVENT_TYPE = 'wix.ecom.v1.order_canceled';

    public function handle(WixUser $wixUser, string $wixOrderId): void
    {
        DB::transaction(function () use ($wixUser, $wixOrderId) {
            $order = Order::where('customer_id', $wixUser->customer_id)
                ->where('platform_order_id', $wixOrderId)
                ->whereNot('state', OrderStateEnum::CANCELLED)
                ->first();

            if (!$order) {
                return;
            }

            UpdateOrder::make()->action($order, [
                'internal_notes' => __('Order cancelled by Wix'),
            ]);

            CancelOrder::make()->action($order);
        });
    }

    public function asController(Request $request): JsonResponse
    {
        $event = $this->verifyWixWebhook($request->getContent());

        if (!$event) {
            return response()->json(['message' => 'Invalid Wix webhook signature'], 400);
        }

        if (Arr::get($event, 'eventType') !== self::EVENT_TYPE) {
            return response()->json(['received' => true]);
        }

        $wixUser    = $this->resolveWixUserFromEvent($event);
        $wixOrderId = $this->wixOrderIdFromEvent($event);

        if ($wixUser && $wixOrderId) {
            self::dispatch($wixUser, $wixOrderId);
        }

        return response()->json(['received' => true]);
    }
}
