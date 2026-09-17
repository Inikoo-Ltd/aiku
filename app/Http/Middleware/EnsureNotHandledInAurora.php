<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 17 Sept 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use App\Models\Dispatching\DeliveryNote;
use App\Models\Ordering\Order;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Orders submitted in Aurora before a shop moved to aiku are finished in Aurora and fetched
 * afterwards. Until they are dispatched or cancelled, staff routes must not touch them, or the
 * warehouse would process the same order twice. Aurora fetches never pass through here.
 */
class EnsureNotHandledInAurora
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethodSafe()) {
            return $next($request);
        }

        $order        = $request->route('order');
        $deliveryNote = $request->route('deliveryNote')
            ?? $request->route('deliveryNoteItem')?->deliveryNote
            ?? $request->route('picking')?->deliveryNote;

        $locked = ($order instanceof Order && $order->isLockedInAurora())
            || ($deliveryNote instanceof DeliveryNote && $deliveryNote->isLockedInAurora());

        $deliveryNoteIds = array_filter((array)$request->input('delivery_notes', []), 'is_numeric');
        if (!$locked && $deliveryNoteIds) {
            $locked = DeliveryNote::whereIn('id', $deliveryNoteIds)->where('handled_in_aurora', true)->get()
                ->contains(fn (DeliveryNote $deliveryNote) => $deliveryNote->isLockedInAurora());
        }

        if ($locked) {
            throw ValidationException::withMessages([
                'message' => __('This order was submitted in Aurora and must be finished in Aurora. It will appear here once Aurora dispatches or cancels it.')
            ]);
        }

        return $next($request);
    }
}
