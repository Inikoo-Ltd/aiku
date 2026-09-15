<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\CRM\TrafficSource;

use App\Enums\CRM\TrafficSource\TrafficSourceAudienceEnum;
use App\Models\Catalogue\Shop;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Who a channel's advertising actually reached, split by what each person was when they clicked.
 *
 * Two questions, answered from opposite directions. For somebody signed in at the time the answer was
 * written onto the click and is simply read back. For everybody else the answer only exists later, so
 * the click is followed forward: session to visitor, visitor to every other session that visitor ever
 * had, those sessions to a sign-in, and the sign-in to a customer that did not exist when they
 * clicked.
 *
 * The forward half is the reason the two halves are separate queries. Reading back is an index lookup;
 * following forward is four joins over the whole visitor history, and running them together would
 * make the cheap answer pay for the expensive one.
 */
class GetTrafficSourceAudienceMix
{
    use AsObject;

    /**
     * How long after a click a registration still counts as caused by it. Google's own default for a
     * search click is thirty days and matching it means our number and theirs can be argued about on
     * the same terms. Wrong for a trade account that takes two months to decide, so it is a parameter.
     */
    public const int DEFAULT_WINDOW_DAYS = 30;

    /**
     * @return array{
     *     buckets: array<int, array{key: string, label: string, description: string, colour: string, count: int, share: float, is_acquisition: bool}>,
     *     total: int,
     *     identified: int,
     *     acquisition: int,
     *     window_days: int,
     *     measured_from: string|null
     * }
     */
    public function handle(
        Shop $shop,
        ?string $type = null,
        ?string $campaignRef = null,
        ?Carbon $from = null,
        ?Carbon $to = null,
        int $windowDays = self::DEFAULT_WINDOW_DAYS
    ): array {
        $counts = array_fill_keys(array_column(TrafficSourceAudienceEnum::cases(), 'value'), 0);

        $scoped = fn () => DB::table('traffic_source_clicks as c')
            ->where('c.shop_id', $shop->id)
            ->where('c.is_bot', false)
            ->when($type, fn ($query) => $query->where('c.type', $type))
            ->when($campaignRef, fn ($query) => $query->where('c.campaign_ref', $campaignRef))
            ->when($from, fn ($query) => $query->where('c.created_at', '>=', $from))
            ->when($to, fn ($query) => $query->where('c.created_at', '<=', $to));

        /* Whether they had bought before the click is asked of first_order_date rather than frozen
           onto the click, because a first order is a date that never moves. The state beside it does
           move, which is why that one had to be written down at the time. */
        $known = $scoped()
            ->join('customer_stats as cs', 'cs.customer_id', '=', 'c.customer_id')
            ->whereNotNull('c.customer_id')
            ->selectRaw('c.customer_state, (cs.first_order_date IS NOT NULL AND cs.first_order_date <= c.created_at) as had_ordered, count(*) as total')
            ->groupBy('c.customer_state', 'had_ordered')
            ->get();

        foreach ($known as $row) {
            $counts[TrafficSourceAudienceEnum::whenKnown($row->customer_state, (bool) $row->had_ordered)->value] += (int) $row->total;
        }

        $anonymous = (int) $scoped()->whereNull('c.customer_id')->count();

        foreach ($this->resolveForward($scoped(), $windowDays) as $row) {
            $counts[TrafficSourceAudienceEnum::whenAcquired((bool) $row->has_ordered)->value] += (int) $row->total;
            $anonymous                                                                        -= (int) $row->total;
        }

        $counts[TrafficSourceAudienceEnum::ANONYMOUS->value] = max($anonymous, 0);

        $total = array_sum($counts);

        $buckets = collect(TrafficSourceAudienceEnum::cases())
            ->map(fn (TrafficSourceAudienceEnum $case) => [
                'key'            => $case->value,
                'label'          => TrafficSourceAudienceEnum::labels()[$case->value],
                'description'    => TrafficSourceAudienceEnum::descriptions()[$case->value],
                'colour'         => $case->colour(),
                'count'          => $counts[$case->value],
                'share'          => $total > 0 ? round($counts[$case->value] / $total * 100, 1) : 0.0,
                'is_acquisition' => $case->isAcquisition(),
            ])
            ->values()
            ->all();

        return [
            'buckets'       => $buckets,
            'total'         => $total,
            'identified'    => $total - $counts[TrafficSourceAudienceEnum::ANONYMOUS->value],
            'acquisition'   => $counts[TrafficSourceAudienceEnum::NEW_CUSTOMER->value]
                + $counts[TrafficSourceAudienceEnum::GUEST_REGISTERED->value],
            'window_days'   => $windowDays,
            'measured_from' => $this->measuredFrom($shop, $type),
        ];
    }

    /**
     * Anonymous clicks that turned into a customer inside the window.
     *
     * The hop through sibling sessions is what lets somebody click an ad on Tuesday and register from
     * the same browser a fortnight later without the two ever sharing a session id. DISTINCT ON keeps
     * one row per click, the earliest customer, so a shared machine cannot count one click twice.
     *
     * @return \Illuminate\Support\Collection<int, object{has_ordered: bool, total: int}>
     */
    private function resolveForward(\Illuminate\Database\Query\Builder $scoped, int $windowDays): \Illuminate\Support\Collection
    {
        $earliestPerClick = $scoped
            ->selectRaw('DISTINCT ON (c.id) c.id, (cs.first_order_date IS NOT NULL) as has_ordered')
            ->join('website_visitors as v', 'v.session_id', '=', 'c.session_id')
            ->join('website_visitors as sibling', function ($join) {
                $join->on('sibling.visitor_hash', '=', 'v.visitor_hash')
                    ->on('sibling.website_id', '=', 'v.website_id');
            })
            ->join('web_users', 'web_users.id', '=', 'sibling.web_user_id')
            ->join('customers', 'customers.id', '=', 'web_users.customer_id')
            ->join('customer_stats as cs', 'cs.customer_id', '=', 'customers.id')
            ->whereNull('c.customer_id')
            ->whereNotNull('c.session_id')
            ->whereColumn('customers.created_at', '>=', 'c.created_at')
            ->whereRaw("customers.created_at <= c.created_at + (? * interval '1 day')", [$windowDays])
            ->orderBy('c.id')
            ->orderBy('customers.created_at');

        return DB::query()
            ->fromSub($earliestPerClick, 'resolved')
            ->selectRaw('has_ordered, count(*) as total')
            ->groupBy('has_ordered')
            ->get();
    }

    /**
     * Clicks are pruned after ninety days because they hold IP addresses, so a share of a channel's
     * traffic is always older than anything this can classify. Returned so the page can say which
     * period the split describes instead of implying it covers everything ever spent.
     */
    private function measuredFrom(Shop $shop, ?string $type): ?string
    {
        $earliest = DB::table('traffic_source_clicks')
            ->where('shop_id', $shop->id)
            ->when($type, fn ($query) => $query->where('type', $type))
            ->min('created_at');

        return $earliest ? Carbon::parse($earliest)->toDateString() : null;
    }
}
