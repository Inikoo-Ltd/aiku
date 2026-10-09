<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use App\Models\CRM\Customer;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use App\Models\Procurement\OrgPartner;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * A partner buys from a manufacturing hub through its shopping list: every line of the hub's order
 * for it stands for a line on that list. An order, a line or extra quantity keyed in by hand has no
 * line behind it, so the buyer's list keeps asking for goods that already left and the hub makes
 * them twice (HELP-3848). Removing a line or lowering a quantity stays open, the list takes those back.
 * The message travels as a validation error so both Inertia forms and axios callers show it.
 */
class EnsureHubPartnerOrderFromShoppingList
{
    private const ALWAYS_BLOCKED_ROUTES = [
        'customer.order.store',
        'customer.submitted_order.store',
        'order.transaction.store',
        'order.transaction.upload',
        'order.follow_up.store',
    ];

    private const QUANTITY_ROUTES = [
        'transaction.update',
        'transaction.update_quantity_ordered',
        'order.modification.save',
    ];

    public function handle(Request $request, Closure $next)
    {
        $routeName = Str::after((string) $request->route()?->getName(), 'grp.models.');

        $isAlwaysBlocked = in_array($routeName, self::ALWAYS_BLOCKED_ROUTES, true);
        if (!$isAlwaysBlocked && !in_array($routeName, self::QUANTITY_ROUTES, true)) {
            return $next($request);
        }

        $customer = $this->customer($request);
        if (!$customer || !self::isHubPartnerCustomer($customer)) {
            return $next($request);
        }

        if ($isAlwaysBlocked || $this->raisesQuantity($request, $routeName)) {
            throw ValidationException::withMessages([
                'order' => __('Orders for :partner come from their shopping list. Send this from the partner shopping list instead of adding it here.', [
                    'partner' => $customer->name,
                ]),
            ]);
        }

        return $next($request);
    }

    public static function isHubPartnerCustomer(Customer $customer): bool
    {
        if (!$customer->organisation->is_manufacturing_hub) {
            return false;
        }

        return OrgPartner::where('partner_id', $customer->organisation_id)
            ->get(['id', 'data'])
            ->contains(fn (OrgPartner $orgPartner) => in_array($customer->id, data_get($orgPartner->data, 'intercompany_customers', [])));
    }

    private function customer(Request $request): ?Customer
    {
        $route = $request->route();

        return match (true) {
            $route->parameter('customer') instanceof Customer => $route->parameter('customer'),
            $route->parameter('order') instanceof Order => $route->parameter('order')->customer,
            $route->parameter('transaction') instanceof Transaction => $route->parameter('transaction')->order?->customer,
            default => null,
        };
    }

    private function raisesQuantity(Request $request, string $routeName): bool
    {
        if ($routeName === 'order.modification.save') {
            /** @var Order $order */
            $order = $request->route()->parameter('order');

            if (!empty($request->input('products'))) {
                return true;
            }

            $transactions = $order->transactions()->whereIn('id', array_keys((array) $request->input('transactions', [])))->get()->keyBy('id');

            return collect((array) $request->input('transactions', []))
                ->contains(fn ($data, $transactionId) => $this->isRaise((float) Arr::get($data, 'newQty'), $transactions->get($transactionId)?->quantity_ordered));
        }

        /** @var Transaction $transaction */
        $transaction = $request->route()->parameter('transaction');

        if ($request->has('units_ordered')) {
            $units = (float) ($transaction->model?->units ?: 1);

            return $this->isRaise((float) $request->input('units_ordered') / $units, $transaction->quantity_ordered);
        }

        return $request->has('quantity_ordered') && $this->isRaise((float) $request->input('quantity_ordered'), $transaction->quantity_ordered);
    }

    private function isRaise(float $requested, mixed $current): bool
    {
        return round($requested, 6) > round((float) $current, 6);
    }
}
