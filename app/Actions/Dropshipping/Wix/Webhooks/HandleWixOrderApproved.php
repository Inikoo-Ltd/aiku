<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Wix\Webhooks;

use App\Actions\Dropshipping\Wix\Order\ValidateIncomingWixOrder;
use App\Models\Dropshipping\WixUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class HandleWixOrderApproved
{
    use AsAction;
    use WithWixOrderWebhook;

    public const string EVENT_TYPE = 'wix.ecom.v1.order_approved';

    /**
     * @throws \Exception
     */
    public function handle(WixUser $wixUser, string $wixOrderId): void
    {
        $response = $wixUser->getOrder($wixOrderId);

        $order = Arr::get($response, 'order');

        if (!is_array($order)) {
            throw new \Exception('Could not read Wix order '.$wixOrderId.': '.Arr::get($response, 'message', 'unknown error'));
        }

        ValidateIncomingWixOrder::run($wixUser, $order);
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
