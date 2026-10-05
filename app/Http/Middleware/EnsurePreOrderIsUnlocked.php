<?php

/*
 * Author Louis Perez
 * Created on 29-09-2026-10h-53m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Http\Middleware;

use App\Models\Ordering\Order;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Guards the money-related writes of an order that is an open pre-order (HELP-3432): the customer
 * paid a deposit for these lines and amounts, so only a member of staff who unlocked it from the
 * pre-order panel can change them. Notes, payments, attachments and the pre-order panel stay open.
 * The message travels as a validation error so both Inertia forms and axios callers show it.
 */
class EnsurePreOrderIsUnlocked
{
    private const LOCKED_ROUTES = [
        'transaction.delete',
        'transaction.update',
        'transaction.update_quantity_ordered',
        'transaction.update_units',
        'transaction.update_discretionary_discount',
        'transaction.remove_discount',
        'transaction.update_charge_amount',
        'order.discretionary_charge_transaction',
        'order.update',
        'order.update_premium_dispatch',
        'order.update_extra_packing',
        'order.update_gift_message',
        'order.update_insurance',
        'order.delivery_address_update',
        'order.billing_address_update',
        'order.address.switch',
        'order.modification.save',
        'order.discount.update',
        'order.discount.removal',
        'order.add_voucher',
        'order.remove_voucher',
        'order.basket.collection.store',
        'order.basket.collection.delete',
        'order.transaction.upload',
        'order.transaction.store',
        'order.send_back_to_basket',
        'order.state.creating',
        'order.state.cancelled',
        'order.state.in-warehouse',
        'order.state.in-warehouse-unpaid',
        'order.set_shipping_engine_manual',
        'order.set_shipping_tbc_amount',
        'order.set_shipping_engine_auto',
        'order.recalculate-vat',
    ];

    private const OPEN_ORDER_UPDATE_FIELDS = [
        'shipping_notes',
        'customer_notes',
        'public_notes',
        'internal_notes',
        'private_warehouse_note',
        'contact_name',
        'company_name',
    ];

    public function handle(Request $request, Closure $next)
    {
        $routeName = Str::after((string) $request->route()?->getName(), 'grp.models.');

        if (!in_array($routeName, self::LOCKED_ROUTES, true)) {
            return $next($request);
        }

        if ($routeName === 'order.update' && empty(array_diff(array_keys($request->except(['_method'])), self::OPEN_ORDER_UPDATE_FIELDS))) {
            return $next($request);
        }

        $order    = $request->route('order') ?? $request->route('transaction')?->order;
        $preOrder = $order instanceof Order ? $order->preOrder : null;

        if ($preOrder && !$preOrder->canBeEditedBy($request->user())) {
            throw ValidationException::withMessages(['message' => $preOrder->lockMessage()]);
        }

        return $next($request);
    }
}
