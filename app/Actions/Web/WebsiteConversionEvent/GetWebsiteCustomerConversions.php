<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Mon, 02 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\WebsiteConversionEvent;

use App\Enums\Web\WebsiteConversionEvent\WebsiteConversionEventTypeEnum;
use App\Models\CRM\Customer;
use App\Models\Web\Website;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * What one customer did on the website in the period, behind a count of the Performance tab.
 * Add to basket: one row per product, with how many times and how many units. Checkout and
 * purchase: each event with its date and the order's products as the order stands now. An add
 * to basket has no order, so it is the customer's through the web user signed in at the time.
 */
class GetWebsiteCustomerConversions
{
    use AsAction;

    /**
     * @return array{customer_id: int, type: string, customer: array{name: string|null, route: array{name: string, parameters: array<int, string>}}, from: string|null, to: string|null, items: array<int, array{code: string|null, name: string|null, route: array{name: string, parameters: array<int, string>}|null, adds: int, quantity: int, last_added_at: string}>, orders: array<int, array{event_id: int, event_at: string, reference: string|null, route: array{name: string, parameters: array<int, string>}, net_amount: float, currency_code: string|null, items: array<int, array{code: string|null, name: string|null, route: array{name: string, parameters: array<int, string>}|null, quantity: float}>}>}|null
     */
    public function handle(Website $website, int $customerId, WebsiteConversionEventTypeEnum $type, ?string $fromDate = null, ?string $toDate = null): ?array
    {
        $customer = Customer::where('shop_id', $website->shop_id)->find($customerId, ['id', 'slug', 'name', 'contact_name', 'email']);

        if (!$customer) {
            return null;
        }

        $organisationSlug = $website->organisation->slug;
        $shopSlug         = $website->shop->slug;

        $productRoute = fn (?string $productSlug) => $productSlug ? [
            'name'       => 'grp.org.shops.show.catalogue.products.all_products.show',
            'parameters' => [$organisationSlug, $shopSlug, $productSlug],
        ] : null;

        return [
            'customer_id' => $customer->id,
            'type'        => $type->value,
            'customer'    => [
                'name'  => $customer->name ?: ($customer->contact_name ?: $customer->email),
                'route' => [
                    'name'       => 'grp.org.shops.show.crm.customers.show',
                    'parameters' => [$organisationSlug, $shopSlug, $customer->slug],
                ],
            ],
            'from'        => $fromDate,
            'to'          => $toDate,
            'items'       => $type === WebsiteConversionEventTypeEnum::ADD_TO_BASKET
                ? $this->basketItems($website, $customer, $fromDate, $toDate)->map(fn (object $item) => [
                    'code'          => $item->code,
                    'name'          => $item->name,
                    'route'         => $productRoute($item->product_slug),
                    'adds'          => (int) $item->adds,
                    'quantity'      => (int) $item->quantity,
                    'last_added_at' => Carbon::parse($item->last_added_at)->toIso8601String(),
                ])->all()
                : [],
            'orders'      => $type === WebsiteConversionEventTypeEnum::ADD_TO_BASKET
                ? []
                : $this->orderEvents($website, $customer, $type, $fromDate, $toDate, $organisationSlug, $shopSlug, $productRoute),
        ];
    }

    private function basketItems(Website $website, Customer $customer, ?string $fromDate, ?string $toDate): Collection
    {
        return DB::connection('aiku_no_sticky')->table('website_conversion_events as ce')
            ->join('website_visitors as v', 'v.id', '=', 'ce.website_visitor_id')
            ->join('web_users as wu', 'wu.id', '=', 'v.web_user_id')
            ->leftJoin('assets as a', 'a.id', '=', 'ce.product_id')
            ->leftJoin('products as p', fn ($join) => $join->on('p.id', '=', 'a.model_id')->where('a.model_type', 'Product'))
            ->where('ce.website_id', $website->id)
            ->where('ce.event_type', WebsiteConversionEventTypeEnum::ADD_TO_BASKET->value)
            ->where('wu.customer_id', $customer->id)
            ->when($fromDate, fn ($query) => $query->where('ce.event_date', '>=', Carbon::parse($fromDate)->toDateString()))
            ->when($toDate, fn ($query) => $query->where('ce.event_date', '<=', Carbon::parse($toDate)->toDateString()))
            ->groupBy('ce.product_id', 'a.code', 'a.name', 'p.slug')
            ->select('ce.product_id', 'a.code', 'a.name', 'p.slug as product_slug')
            ->selectRaw('COUNT(*) as adds')
            ->selectRaw('SUM(ce.quantity) as quantity')
            ->selectRaw('MAX(ce.created_at) as last_added_at')
            ->orderByDesc('last_added_at')
            ->get();
    }

    /**
     * @param  \Closure(string|null): (array{name: string, parameters: array<int, string>}|null)  $productRoute
     * @return array<int, array<string, mixed>>
     */
    private function orderEvents(Website $website, Customer $customer, WebsiteConversionEventTypeEnum $type, ?string $fromDate, ?string $toDate, string $organisationSlug, string $shopSlug, \Closure $productRoute): array
    {
        $events = DB::connection('aiku_no_sticky')->table('website_conversion_events as ce')
            ->join('orders as o', 'o.id', '=', 'ce.order_id')
            ->leftJoin('currencies as cur', 'cur.id', '=', 'o.currency_id')
            ->where('ce.website_id', $website->id)
            ->where('ce.event_type', $type->value)
            ->where('o.customer_id', $customer->id)
            ->when($fromDate, fn ($query) => $query->where('ce.event_date', '>=', Carbon::parse($fromDate)->toDateString()))
            ->when($toDate, fn ($query) => $query->where('ce.event_date', '<=', Carbon::parse($toDate)->toDateString()))
            ->orderByDesc('ce.created_at')
            ->get([
                'ce.id as event_id',
                'ce.created_at as event_at',
                'o.id as order_id',
                'o.reference',
                'o.slug as order_slug',
                'cur.code as currency_code',
                DB::raw('COALESCE(ce.net_amount, o.net_amount) as net_amount'),
            ]);

        $itemsByOrder = DB::connection('aiku_no_sticky')->table('transactions as t')
            ->leftJoin('assets as a', 'a.id', '=', 't.asset_id')
            ->leftJoin('products as p', fn ($join) => $join->on('p.id', '=', 'a.model_id')->where('a.model_type', 'Product'))
            ->whereIn('t.order_id', $events->pluck('order_id')->unique()->all())
            ->where('t.model_type', 'Product')
            ->whereNull('t.deleted_at')
            ->where('t.quantity_ordered', '>', 0)
            ->orderBy('t.id')
            ->get(['t.order_id', 'a.code', 'a.name', 'p.slug as product_slug', 't.quantity_ordered'])
            ->groupBy('order_id');

        return $events->map(fn (object $event) => [
            'event_id'      => (int) $event->event_id,
            'event_at'      => Carbon::parse($event->event_at)->toIso8601String(),
            'reference'     => $event->reference,
            'route'         => [
                'name'       => 'grp.org.shops.show.ordering.orders.show',
                'parameters' => [$organisationSlug, $shopSlug, $event->order_slug],
            ],
            'net_amount'    => (float) $event->net_amount,
            'currency_code' => $event->currency_code,
            'items'         => $itemsByOrder->get($event->order_id, collect())->map(fn (object $item) => [
                'code'     => $item->code,
                'name'     => $item->name,
                'route'    => $productRoute($item->product_slug),
                'quantity' => (float) $item->quantity_ordered,
            ])->values()->all(),
        ])->all();
    }
}
