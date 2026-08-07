<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\TrafficSource;

use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\UI\Marketing\MarketingPeriodEnum;
use App\Models\Catalogue\Shop;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class GetShopOfferPerformance
{
    use AsAction;

    /**
     * What each offer actually did, and whether emailing about it made any difference.
     *
     * Nobody has to declare what a mailshot was for. Which offers its recipients went on to use is
     * already in the data, so the goal is observed rather than configured - and the interesting case,
     * "we promoted one offer and they redeemed a different one", only exists when it is observed.
     *
     * The number that matters is the last one: uptake among customers we emailed in the period against
     * uptake among everyone else. A plain redemption count flatters every offer, because the customers
     * who would have reordered anyway redeem whatever is live at the time. Uptake of 3.1% against 2.9%
     * is an offer that would have been redeemed without the email.
     *
     * Correlation, not proof: a recipient who was going to reorder that week and used a live offer
     * still lands in the emailed column. Lift is what separates that from a mailshot doing the work.
     *
     * @return array{period: string, period_label: string, from: string|null, currency_code: string, reach: array{emailed: int, customers: int}, offers: array<int, array{name: string, code: string|null, orders: int, customers: int, discount: float, revenue: float, emailed_customers: int, uptake_emailed: float|null, uptake_rest: float|null, lift: float|null}>}
     */
    public function handle(Shop $shop, MarketingPeriodEnum $period = MarketingPeriodEnum::LAST_30): array
    {
        $from = $period->startsAt();

        $customers = DB::table('customers')->where('shop_id', $shop->id)->count();
        $emailed   = $this->emailedCustomerIds($shop, $from);
        $rest      = max($customers - $emailed->count(), 0);

        $offers = $this->offerTotals($shop, $from);
        $users  = $this->offerUsersEmailed($shop, $from, $emailed);

        return [
            'period'        => $period->value,
            'period_label'  => MarketingPeriodEnum::labels()[$period->value],
            'from'          => $from?->toDateString(),
            'currency_code' => $shop->currency->code,
            'reach'         => [
                'emailed'   => $emailed->count(),
                'customers' => $customers,
            ],
            'offers'        => $offers
                ->map(function ($offer) use ($users, $emailed, $rest) {
                    $emailedUsers = (int) ($users[$offer->id] ?? 0);
                    $restUsers    = max((int) $offer->customers - $emailedUsers, 0);

                    $uptakeEmailed = $emailed->count() > 0 ? round($emailedUsers / $emailed->count() * 100, 2) : null;
                    $uptakeRest    = $rest > 0 ? round($restUsers / $rest * 100, 2) : null;

                    return [
                        'name'              => $offer->name,
                        'code'              => $offer->code,
                        'orders'            => (int) $offer->orders,
                        'customers'         => (int) $offer->customers,
                        'discount'          => round((float) $offer->discount, 2),
                        'revenue'           => round((float) $offer->revenue, 2),
                        'emailed_customers' => $emailedUsers,
                        'uptake_emailed'    => $uptakeEmailed,
                        'uptake_rest'       => $uptakeRest,
                        'lift'              => ($uptakeEmailed !== null && $uptakeRest > 0)
                            ? round($uptakeEmailed / $uptakeRest, 2)
                            : null,
                    ];
                })
                ->sortByDesc('revenue')
                ->values()
                ->all(),
        ];
    }

    /**
     * Customers sent any marketing email in the period. The control group is everybody else in the
     * shop - not everybody *eligible* for the offer, which would be more correct and much more
     * expensive; the screen says so plainly rather than pretending otherwise.
     *
     * @return Collection<int, int>
     */
    private function emailedCustomerIds(Shop $shop, ?Carbon $from): Collection
    {
        return DB::table('dispatched_emails as de')
            ->join('model_has_dispatched_emails as mde', function ($join) {
                $join->on('mde.dispatched_email_id', '=', 'de.id')
                    ->where('mde.model_type', '=', 'Customer');
            })
            ->join('outboxes as o', 'o.id', '=', 'de.outbox_id')
            ->where('o.shop_id', $shop->id)
            ->whereIn('o.code', array_merge(
                ['newsletter', 'marketing'],
                (array) config('marketing.attributed_outbox_codes', [])
            ))
            ->when($from, fn ($query) => $query->where('de.created_at', '>=', $from))
            ->distinct()
            ->pluck('mde.model_id');
    }

    /**
     * @return Collection<int, object>
     */
    private function offerTotals(Shop $shop, ?Carbon $from): Collection
    {
        /* Grouped from distinct orders on purpose: an offer allowance is written per transaction, so
           counting rows would report a basket of twelve discounted lines as twelve redemptions. */
        return DB::table('offers as of')
            ->join('transaction_has_offer_allowances as a', 'a.offer_id', '=', 'of.id')
            ->join('orders as ord', 'ord.id', '=', 'a.order_id')
            ->where('of.shop_id', $shop->id)
            ->whereNotIn('ord.state', [OrderStateEnum::CREATING, OrderStateEnum::CANCELLED])
            ->whereNull('ord.deleted_at')
            ->when($from, fn ($query) => $query->where('ord.date', '>=', $from))
            ->groupBy('of.id', 'of.name', 'of.code')
            ->select(
                'of.id',
                'of.name',
                'of.code',
                DB::raw('COUNT(DISTINCT ord.id) as orders'),
                DB::raw('COUNT(DISTINCT ord.customer_id) as customers'),
                DB::raw('SUM(a.discounted_amount) as discount'),
                DB::raw('(SELECT COALESCE(SUM(o2.net_amount), 0) FROM orders o2 WHERE o2.id IN (SELECT DISTINCT a2.order_id FROM transaction_has_offer_allowances a2 WHERE a2.offer_id = of.id AND a2.order_id = o2.id)) as revenue'),
            )
            ->get();
    }

    /**
     * How many of each offer's redeemers we had emailed in the period.
     *
     * @param Collection<int, int> $emailed
     *
     * @return Collection<int, int>
     */
    private function offerUsersEmailed(Shop $shop, ?Carbon $from, Collection $emailed): Collection
    {
        if ($emailed->isEmpty()) {
            return collect();
        }

        return DB::table('offers as of')
            ->join('transaction_has_offer_allowances as a', 'a.offer_id', '=', 'of.id')
            ->join('orders as ord', 'ord.id', '=', 'a.order_id')
            ->where('of.shop_id', $shop->id)
            ->whereIn('ord.customer_id', $emailed)
            ->whereNotIn('ord.state', [OrderStateEnum::CREATING, OrderStateEnum::CANCELLED])
            ->whereNull('ord.deleted_at')
            ->when($from, fn ($query) => $query->where('ord.date', '>=', $from))
            ->groupBy('of.id')
            ->select('of.id', DB::raw('COUNT(DISTINCT ord.customer_id) as customers'))
            ->pluck('customers', 'id');
    }
}
