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
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The products one customer added to the basket on the website in the period, one row per product
 * with how many times and how many units, most recently added first. An add to basket has no order,
 * so it is the customer's through the web user signed in at the time.
 */
class GetWebsiteCustomerBasketItems
{
    use AsAction;

    /**
     * @return array{customer_id: int, customer: array{name: string|null, route: array{name: string, parameters: array<int, string>}}, from: string|null, to: string|null, items: array<int, array{code: string|null, name: string|null, route: array{name: string, parameters: array<int, string>}|null, adds: int, quantity: int, last_added_at: string}>}|null
     */
    public function handle(Website $website, int $customerId, ?string $fromDate = null, ?string $toDate = null): ?array
    {
        $customer = Customer::where('shop_id', $website->shop_id)->find($customerId, ['id', 'slug', 'name', 'contact_name', 'email']);

        if (!$customer) {
            return null;
        }

        $items = DB::connection('aiku_no_sticky')->table('website_conversion_events as ce')
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

        $organisationSlug = $website->organisation->slug;
        $shopSlug         = $website->shop->slug;

        return [
            'customer_id' => $customer->id,
            'customer'    => [
                'name'  => $customer->name ?: ($customer->contact_name ?: $customer->email),
                'route' => [
                    'name'       => 'grp.org.shops.show.crm.customers.show',
                    'parameters' => [$organisationSlug, $shopSlug, $customer->slug],
                ],
            ],
            'from'        => $fromDate,
            'to'          => $toDate,
            'items'       => $items->map(fn (object $item) => [
                'code'          => $item->code,
                'name'          => $item->name,
                'route'         => $item->product_slug ? [
                    'name'       => 'grp.org.shops.show.catalogue.products.all_products.show',
                    'parameters' => [$organisationSlug, $shopSlug, $item->product_slug],
                ] : null,
                'adds'          => (int) $item->adds,
                'quantity'      => (int) $item->quantity,
                'last_added_at' => Carbon::parse($item->last_added_at)->toIso8601String(),
            ])->all(),
        ];
    }
}
