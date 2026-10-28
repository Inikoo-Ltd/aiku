<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 19 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\SocialMedia;

use App\Actions\CRM\TrafficSource\WithAggregatedChannelQueries;
use App\Enums\CRM\SocialMedia\SocialMediaPlatformEnum;
use App\Enums\CRM\SocialMedia\SocialMediaPostTypeEnum;
use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use App\Actions\Helpers\CurrencyExchange\GetHistoricCurrencyExchange;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Currency;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Only Meta is offered for now, Facebook and Instagram, organic and paid: TikTok is left out of the
 * platforms and the paid channels until its figures are synced too.
 *
 * Organic figures are the posts published in the period, as last exported; followers come from the
 * accounts' daily exports. Paid figures are the Meta ad spend fetched every night and the revenue
 * attribution credits to it, in the parent's currency.
 *
 * Views, interactions, link clicks and visits are the accounts' own daily figures where an account
 * has them for the period, and the sum of its posts where it does not: a page's daily views also
 * count what it shows outside its posts of the period. Viewers are people, counted once a day, so a
 * period is given as the average day rather than a sum that would count the same person every day.
 */
class GetSocialMediaPerformance
{
    use AsAction;
    use WithAggregatedChannelQueries;

    public const string ENGAGEMENTS = '(p.reactions + p.comments + p.shares + p.saves)';

    private const int TOP_POSTS = 50;

    /**
     * @param array{shop?: string|null, country?: string|null, platform?: string|null, compare?: string, compare_from?: string|null, compare_to?: string|null} $filters
     */
    public function handle(Organisation|Group $parent, ?Carbon $from, ?Carbon $to, array $filters = []): array
    {
        $allShops = $parent->shops()->with('country')->orderBy('name')->get();
        $shops    = $this->filteredShops($allShops, $filters);
        $platform = SocialMediaPlatformEnum::tryFrom((string) ($filters['platform'] ?? ''));

        [$previousFrom, $previousTo] = $this->comparisonPeriod($from, $to, $filters);

        $isOrganisation = $parent instanceof Organisation;
        $costColumn     = $isOrganisation ? 'org_amount' : 'grp_amount';
        $revenueColumn  = $isOrganisation ? 'org_net_amount' : 'grp_net_amount';
        $adTypes        = $this->adTypes($platform);

        $organic         = $this->organicTotals($shops, $platform, $from, $to);
        $previousOrganic = $previousFrom ? $this->organicTotals($shops, $platform, $previousFrom, $previousTo) : null;
        $paidChannels    = $this->paidChannels($parent->currency, $shops, $adTypes, $from, $to, $costColumn, $revenueColumn);
        $paid            = $this->paidTotals($paidChannels);
        $previousPaid    = $previousFrom ? $this->paidTotals($this->paidChannels($parent->currency, $shops, $adTypes, $previousFrom, $previousTo, $costColumn, $revenueColumn)) : null;

        $platforms = $this->platforms($shops, $platform, $from, $to);
        $formats   = $this->formats($shops, $platform, $from, $to);

        return [
            'from'          => $from?->toDateString(),
            'to'            => $to?->toDateString(),
            'previous_from' => $previousFrom?->toDateString(),
            'previous_to'   => $previousTo?->toDateString(),
            'currency_code' => $parent->currency->code,
            'filters'       => [
                'shop'         => $filters['shop'] ?? null,
                'country'      => $filters['country'] ?? null,
                'platform'     => $platform?->value,
                'compare'      => $filters['compare'] ?? 'previous',
                'compare_from' => $filters['compare_from'] ?? null,
                'compare_to'   => $filters['compare_to'] ?? null,
            ],
            'options'       => $this->options($allShops),
            'data_until'    => $this->dataUntil($allShops),
            'kpis'          => [
                'current'  => array_merge($organic, $paid),
                'previous' => $previousOrganic ? array_merge($previousOrganic, $previousPaid) : null,
            ],
            'trend'         => $trend = $this->trend($shops, $platform, $from, $to),
            'previous_trend' => $previousFrom ? $this->trend($shops, $platform, $previousFrom, $previousTo, $trend['bucket']) : null,
            'platforms'     => $platforms,
            'formats'       => $formats,
            'insight'       => $this->insight($organic, $previousOrganic, $paid, $platforms, $formats),
            'top_posts'     => $this->topPosts($shops, $platform, $from, $to),
            'accounts'      => $this->accounts($shops, $platform, $from, $to),
            'paid'          => [
                'channels'  => $paidChannels,
                'campaigns' => $this->paidCampaigns($parent, $shops, $adTypes, $from, $to, $costColumn, $revenueColumn),
            ],
        ];
    }

    /**
     * Every post in the period under the filters, for the export.
     *
     * @param array{shop?: string|null, country?: string|null, platform?: string|null} $filters
     */
    public function exportQuery(Organisation|Group $parent, ?Carbon $from, ?Carbon $to, array $filters = []): Builder
    {
        return $this->posts(
            $this->filteredShops($parent->shops()->with('country')->get(), $filters),
            SocialMediaPlatformEnum::tryFrom((string) ($filters['platform'] ?? '')),
            $from,
            $to
        )
            ->join('shops as s', 's.id', '=', 'a.shop_id')
            ->leftJoin('countries as co', 'co.id', '=', 's.country_id')
            ->selectRaw('p.published_at, a.platform, a.name as account_name, s.name as shop_name, co.code as country_code, p.type, p.description, p.permalink, p.reach, p.impressions, p.reactions, p.comments, p.shares, p.saves, p.link_clicks, p.follows, '.self::ENGAGEMENTS.' as engagements')
            ->orderBy('p.published_at');
    }

    /**
     * @param Collection<int, Shop>                                                    $shops
     * @param array{shop?: string|null, country?: string|null, platform?: string|null} $filters
     *
     * @return Collection<int, Shop>
     */
    private function filteredShops(Collection $shops, array $filters): Collection
    {
        return $shops
            ->when($filters['shop'] ?? null, fn (Collection $shops, string $slug) => $shops->where('slug', $slug))
            ->when($filters['country'] ?? null, fn (Collection $shops, string $code) => $shops->filter(fn (Shop $shop) => $shop->country?->code === $code))
            ->values();
    }

    /**
     * The period the figures are compared with: the same number of days just before (the default),
     * the same dates a year earlier, a range chosen by hand, or none at all.
     *
     * @param array<string, mixed> $filters
     *
     * @return array{0: Carbon|null, 1: Carbon|null}
     */
    private function comparisonPeriod(?Carbon $from, ?Carbon $to, array $filters): array
    {
        return match ($filters['compare'] ?? 'previous') {
            'none'   => [null, null],
            'year'   => $from ? [$from->copy()->subYear()->startOfDay(), ($to ?? now())->copy()->subYear()->endOfDay()] : [null, null],
            'custom' => isset($filters['compare_from'], $filters['compare_to']) && $filters['compare_from'] <= $filters['compare_to']
                ? [Carbon::parse($filters['compare_from'])->startOfDay(), Carbon::parse($filters['compare_to'])->endOfDay()]
                : $this->previousPeriod($from, $to),
            default  => $this->previousPeriod($from, $to),
        };
    }

    /**
     * @return array{0: Carbon|null, 1: Carbon|null}
     */
    private function previousPeriod(?Carbon $from, ?Carbon $to): array
    {
        if (!$from) {
            return [null, null];
        }

        $to ??= now()->endOfDay();
        $days = (int) $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1;

        return [
            $from->copy()->subDays($days)->startOfDay(),
            $from->copy()->subDay()->endOfDay(),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function adTypes(?SocialMediaPlatformEnum $platform): array
    {
        return match ($platform) {
            SocialMediaPlatformEnum::FACEBOOK  => [TrafficSourcesTypeEnum::META_ADS->value],
            SocialMediaPlatformEnum::INSTAGRAM => [TrafficSourcesTypeEnum::INSTAGRAM_ADS->value],
            SocialMediaPlatformEnum::TIKTOK    => [TrafficSourcesTypeEnum::TIKTOK_ADS->value],
            null                               => [TrafficSourcesTypeEnum::META_ADS->value, TrafficSourcesTypeEnum::INSTAGRAM_ADS->value],
        };
    }

    /**
     * @param Collection<int, Shop> $shops
     */
    private function posts(Collection $shops, ?SocialMediaPlatformEnum $platform, ?Carbon $from, ?Carbon $to): Builder
    {
        return DB::table('social_media_posts as p')
            ->join('social_media_accounts as a', 'a.id', '=', 'p.social_media_account_id')
            ->whereIn('a.shop_id', $shops->pluck('id'))
            ->when($platform, fn (Builder $query) => $query->where('a.platform', $platform->value))
            ->when($from, fn (Builder $query) => $query->where('p.published_at', '>=', $from))
            ->when($to, fn (Builder $query) => $query->where('p.published_at', '<=', $to));
    }

    /**
     * @param Collection<int, Shop> $shops
     */
    private function accountsQuery(Collection $shops, ?SocialMediaPlatformEnum $platform): Builder
    {
        return DB::table('social_media_accounts as a')
            ->whereIn('a.shop_id', $shops->pluck('id'))
            ->when($platform, fn (Builder $query) => $query->where('a.platform', $platform->value));
    }

    private function rate(float|int $part, float|int $whole): ?float
    {
        return $whole > 0 ? round($part / $whole * 100, 2) : null;
    }

    /**
     * @param Collection<int, Shop> $shops
     *
     * @return array{posts: int, reach: int, impressions: int, engagements: int, engagement_rate: float|null, views: int, organic_views: int, ad_views: int, unsplit_views: int, interactions: int, interaction_rate: float|null, avg_daily_viewers: int|null, visits: int|null, new_follows: int|null, link_clicks: int, followers: int|null, followers_accounts: int, follower_growth: int|null}
     */
    private function organicTotals(Collection $shops, ?SocialMediaPlatformEnum $platform, ?Carbon $from, ?Carbon $to): array
    {
        $totals = $this->posts($shops, $platform, $from, $to)
            ->selectRaw('COUNT(*) as posts, COALESCE(SUM(p.reach), 0) as reach, COALESCE(SUM(p.impressions), 0) as impressions, COALESCE(SUM('.self::ENGAGEMENTS.'), 0) as engagements, COALESCE(SUM(p.link_clicks), 0) as link_clicks, COALESCE(SUM(p.follows), 0) as follows')
            ->first();

        $accountIds = $this->accountsQuery($shops, $platform)->pluck('a.id');
        $followers  = $this->followersAt($accountIds, $to);
        $before     = $from ? $this->followersAt($accountIds, $from->copy()->subDay()) : collect();

        $newFollows = DB::table('social_media_account_daily_stats')
            ->whereIn('social_media_account_id', $accountIds)
            ->when($from, fn (Builder $query) => $query->where('date', '>=', $from->toDateString()))
            ->when($to, fn (Builder $query) => $query->where('date', '<=', $to->toDateString()))
            ->sum('new_follows');

        $growthFromFollowers = $before->isNotEmpty()
            ? $followers->only($before->keys()->all())->sum() - $before->sum()
            : null;

        $views        = $this->dailyOrPosts($shops, $platform, $from, $to, 'impressions', 'p.impressions');
        $split        = $this->viewsSplit($accountIds, $from, $to)->first();
        $interactions = $this->dailyOrPosts($shops, $platform, $from, $to, 'engagements', self::ENGAGEMENTS);
        $visits       = $this->dailySum($accountIds, $from, $to, 'profile_visits');

        return [
            'posts'             => (int) $totals->posts,
            'reach'             => (int) $totals->reach,
            'impressions'       => (int) $totals->impressions,
            'engagements'       => (int) $totals->engagements,
            'engagement_rate'   => $this->rate((int) $totals->engagements, (int) $totals->reach),
            'views'             => $views,
            'organic_views'     => (int) ($split->organic ?? 0),
            'ad_views'          => (int) ($split->ads ?? 0),
            'unsplit_views'     => max(0, $views - (int) ($split->organic ?? 0) - (int) ($split->ads ?? 0)),
            'interactions'      => $interactions,
            'interaction_rate'  => $this->rate($interactions, $views),
            'avg_daily_viewers' => $this->averageDailyViewers($accountIds, $from, $to),
            'visits'            => $visits,
            'new_follows'       => $this->dailySum($accountIds, $from, $to, 'new_follows'),
            'link_clicks'       => $this->dailyOrPosts($shops, $platform, $from, $to, 'link_clicks', 'p.link_clicks'),
            'followers'         => $followers->isNotEmpty() ? (int) $followers->sum() : null,
            'followers_accounts' => $followers->count(),
            'follower_growth'   => $growthFromFollowers ?? ((int) $newFollows ?: ((int) $totals->follows ?: null)),
        ];
    }

    /**
     * The accounts' daily figure where an account has one in the period, and its posts' where not.
     *
     * @param Collection<int, Shop> $shops
     */
    private function dailyOrPosts(Collection $shops, ?SocialMediaPlatformEnum $platform, ?Carbon $from, ?Carbon $to, string $dailyColumn, string $postExpression): int
    {
        $daily = $this->dailyByAccount($this->accountsQuery($shops, $platform)->pluck('a.id'), $from, $to, $dailyColumn);

        $fromPosts = $this->posts($shops, $platform, $from, $to)
            ->whereNotIn('a.id', $daily->keys()->all())
            ->sum(DB::raw($postExpression));

        return (int) $daily->sum() + (int) $fromPosts;
    }

    /**
     * @return Collection<int, int> account id => the column summed over the period, for the accounts that report it
     */
    private function dailyByAccount(Collection $accountIds, ?Carbon $from, ?Carbon $to, string $column): Collection
    {
        return DB::table('social_media_account_daily_stats')
            ->whereIn('social_media_account_id', $accountIds)
            ->whereNotNull($column)
            ->when($from, fn (Builder $query) => $query->where('date', '>=', $from->toDateString()))
            ->when($to, fn (Builder $query) => $query->where('date', '<=', $to->toDateString()))
            ->groupBy('social_media_account_id')
            ->selectRaw("social_media_account_id, SUM($column) as total")
            ->pluck('total', 'social_media_account_id')
            ->map(fn ($total) => (int) $total);
    }

    /**
     * Views split by where they came from, on the days an account reports both its views and the
     * part ads brought: ads are boosts and campaigns, organic the rest. Days synced before Meta was
     * asked for the split, uploads and posts-only accounts stay out of both and are shown as not
     * split, so an old day never passes for organic.
     *
     * @return Collection<string|int, object{organic: int, ads: int}> bucket date (or 0 for the whole period) => split
     */
    private function viewsSplit(Collection $accountIds, ?Carbon $from, ?Carbon $to, ?string $bucket = null, string $groupColumn = ''): Collection
    {
        $key = $bucket ? "date_trunc('$bucket', date)::date" : ($groupColumn ?: '0');

        return DB::table('social_media_account_daily_stats')
            ->whereIn('social_media_account_id', $accountIds)
            ->whereNotNull('impressions')
            ->whereNotNull('ad_impressions')
            ->when($from, fn (Builder $query) => $query->where('date', '>=', $from->toDateString()))
            ->when($to, fn (Builder $query) => $query->where('date', '<=', $to->toDateString()))
            ->when($key !== '0', fn (Builder $query) => $query->groupByRaw($key))
            ->selectRaw("$key as bucket, SUM(GREATEST(impressions - ad_impressions, 0)) as organic, SUM(LEAST(ad_impressions, impressions)) as ads")
            ->get()
            ->keyBy(fn ($row) => (string) $row->bucket)
            ->map(fn ($row) => (object) ['organic' => (int) $row->organic, 'ads' => (int) $row->ads]);
    }

    private function dailySum(Collection $accountIds, ?Carbon $from, ?Carbon $to, string $column): ?int
    {
        $daily = $this->dailyByAccount($accountIds, $from, $to, $column);

        return $daily->isEmpty() ? null : (int) $daily->sum();
    }

    /**
     * Viewers across the accounts on an average day of the period, over the days that report them.
     */
    private function averageDailyViewers(Collection $accountIds, ?Carbon $from, ?Carbon $to): ?int
    {
        $average = DB::query()
            ->fromSub(
                DB::table('social_media_account_daily_stats')
                    ->whereIn('social_media_account_id', $accountIds)
                    ->whereNotNull('reach')
                    ->when($from, fn (Builder $query) => $query->where('date', '>=', $from->toDateString()))
                    ->when($to, fn (Builder $query) => $query->where('date', '<=', $to->toDateString()))
                    ->groupBy('date')
                    ->selectRaw('date, SUM(reach) as viewers'),
                'days'
            )
            ->avg('viewers');

        return $average === null ? null : (int) round((float) $average);
    }

    /**
     * One sentence on what moved the period, built only from the figures on screen: where the views
     * came from, which format earned the most per post and how views changed.
     *
     * @param array<string, mixed>             $organic
     * @param array<string, mixed>|null        $previous
     * @param array<string, mixed>             $paid
     * @param array<int, array<string, mixed>> $platforms
     * @param array<int, array<string, mixed>> $formats
     */
    private function insight(array $organic, ?array $previous, array $paid, array $platforms, array $formats): ?string
    {
        $parts = [];

        $leader = collect($platforms)->filter(fn (array $row) => $row['views'] > 0)->sortByDesc('views')->first();
        if ($leader && count($platforms) > 1 && $leader['views_share'] !== null) {
            $parts[] = __(':platform brought :share% of views.', ['platform' => $leader['label'], 'share' => round($leader['views_share'])]);
        }

        $bestFormat = collect($formats)->filter(fn (array $row) => $row['posts'] > 0)->sortByDesc('average_reach')->values();
        if ($bestFormat->count() > 1 && $bestFormat[0]['average_reach'] > 0) {
            $parts[] = __(':format reached :reach people per post on average, the most of any format.', ['format' => $bestFormat[0]['label'], 'reach' => number_format($bestFormat[0]['average_reach'])]);
        }

        if ($previous !== null && ($previous['views'] ?? 0) > 0) {
            $change = round(($organic['views'] - $previous['views']) / $previous['views'] * 100);
            if ($change !== 0.0) {
                $parts[] = $change > 0
                    ? __('Views rose :change% on the previous period.', ['change' => abs($change)])
                    : __('Views fell :change% on the previous period.', ['change' => abs($change)]);
            }
        }

        if ($organic['views'] > 0 && $organic['ad_views'] > 0) {
            $parts[] = __('Ads brought :share% of views (:ads of :views); :organic were organic.', [
                'share'   => round($organic['ad_views'] / $organic['views'] * 100),
                'ads'     => number_format($organic['ad_views']),
                'views'   => number_format($organic['views']),
                'organic' => number_format($organic['organic_views']),
            ]);
        } elseif ($organic['views'] > 0 && ($paid['ad_impressions'] ?? 0) > 0) {
            $parts[] = __('Ads were shown :impressions times beside :views views.', ['impressions' => number_format($paid['ad_impressions']), 'views' => number_format($organic['views'])]);
        }

        return $parts === [] ? null : implode(' ', $parts);
    }

    /**
     * The last follower count each account reported on or before the date; the account's current
     * count when no date is given.
     *
     * @return Collection<int, int>
     */
    private function followersAt(Collection $accountIds, ?Carbon $date): Collection
    {
        $fromStats = DB::table('social_media_account_daily_stats')
            ->whereIn('social_media_account_id', $accountIds)
            ->whereNotNull('followers')
            ->when($date, fn (Builder $query) => $query->where('date', '<=', $date->toDateString()))
            ->selectRaw('DISTINCT ON (social_media_account_id) social_media_account_id, followers')
            ->orderBy('social_media_account_id')
            ->orderByDesc('date')
            ->pluck('followers', 'social_media_account_id')
            ->map(fn ($followers) => (int) $followers);

        if ($date !== null && $date->lt(now()->startOfDay())) {
            return $fromStats;
        }

        $current = DB::table('social_media_accounts')
            ->whereIn('id', $accountIds)
            ->whereNotNull('followers')
            ->pluck('followers', 'id')
            ->map(fn ($followers) => (int) $followers);

        return $fromStats->union($current);
    }

    /**
     * @param array<int, array<string, mixed>> $channels
     *
     * @return array{spend: float, revenue: float, orders: float, roas: float|null, cpa: float|null, ad_impressions: int, ad_clicks: int, ctr: float|null, meta_purchases: float, meta_value: float, meta_roas: float|null, meta_registrations: float, meta_cost_per_registration: float|null}
     */
    private function paidTotals(array $channels): array
    {
        $metaValue     = (float) array_sum(array_column($channels, 'meta_value'));
        $metaCost      = (float) array_sum(array_column($channels, 'meta_cost'));
        $registrations    = (float) array_sum(array_column($channels, 'meta_registrations'));
        $registrationCost = (float) array_sum(array_column($channels, 'meta_registration_cost'));

        $spend       = round(array_sum(array_column($channels, 'spend')), 2);
        $revenue     = round(array_sum(array_column($channels, 'revenue')), 2);
        $orders      = round(array_sum(array_column($channels, 'orders')), 2);
        $impressions = (int) array_sum(array_column($channels, 'impressions'));
        $clicks      = (int) array_sum(array_column($channels, 'clicks'));

        return [
            'spend'          => $spend,
            'revenue'        => $revenue,
            'orders'         => $orders,
            'roas'           => $spend > 0 ? round($revenue / $spend, 2) : null,
            'cpa'            => $orders > 0 ? round($spend / $orders, 2) : null,
            'ad_impressions' => $impressions,
            'ad_clicks'      => $clicks,
            'ctr'            => $this->rate($clicks, $impressions),
            'meta_purchases' => round((float) array_sum(array_column($channels, 'meta_purchases')), 2),
            'meta_value'     => round($metaValue, 2),
            'meta_roas'      => $metaCost > 0 ? round($metaValue / $metaCost, 2) : null,
            'meta_registrations'         => round($registrations, 2),
            'meta_cost_per_registration' => $registrations > 0 ? round($registrationCost / $registrations, 2) : null,
        ];
    }

    /**
     * What Meta itself reports the ads sold: its pixel's purchases and their value, divided by the
     * spend in the ad account's own currency, the figures Ads Manager shows. Beside the ROAS above,
     * which is the revenue of the orders Aiku credits to the ads.
     *
     * Cost per registration divides only the spend of days whose registrations were fetched, so
     * days synced before Meta was asked for them do not inflate it.
     *
     * @return array{meta_purchases: float, meta_value: float, meta_cost: float, meta_roas: float|null, meta_registrations: float, meta_registration_cost: float, meta_cost_per_registration: float|null}
     */
    private function metaPurchases(?object $metrics): array
    {
        $value            = (float) ($metrics->meta_value ?? 0);
        $cost             = (float) ($metrics->meta_cost ?? 0);
        $registrations    = (float) ($metrics->meta_registrations ?? 0);
        $registrationCost = (float) ($metrics->meta_registration_cost ?? 0);

        return [
            'meta_purchases'             => round((float) ($metrics->meta_purchases ?? 0), 2),
            'meta_value'                 => round($value, 2),
            'meta_cost'                  => round($cost, 2),
            'meta_roas'                  => $cost > 0 ? round($value / $cost, 2) : null,
            'meta_registrations'         => round($registrations, 2),
            'meta_registration_cost'     => round($registrationCost, 2),
            'meta_cost_per_registration' => $registrations > 0 ? round($registrationCost / $registrations, 2) : null,
        ];
    }

    /**
     * @param array<int, string> $adTypes
     *
     * @return array<int, array{type: string, label: string, spend: float, revenue: float, orders: float, roas: float|null, impressions: int, clicks: int, ctr: float|null}>
     */
    private function paidChannels(Currency $currency, Collection $shops, array $adTypes, ?Carbon $from, ?Carbon $to, string $costColumn, string $revenueColumn): array
    {
        $attributionFrom = $this->clipToAttributionStart($from);
        $metrics         = $this->adMetrics($currency, $shops, $adTypes, $from, $to, 'ts.type');
        $spendByType     = $this->spendByType($shops, $from, $to, $costColumn);
        $revenueByType   = $this->revenueByType($shops, $attributionFrom, $to, $revenueColumn);
        $ordersByType    = $this->ordersByType($shops, $attributionFrom, $to);

        return collect($adTypes)
            ->map(function (string $type) use ($spendByType, $revenueByType, $ordersByType, $metrics) {
                $spend   = (float) ($spendByType[$type] ?? 0);
                $revenue = (float) ($revenueByType[$type] ?? 0);
                $orders  = (float) ($ordersByType[$type] ?? 0);
                $clicks  = (int) ($metrics[$type]->clicks ?? 0);
                $shown   = (int) ($metrics[$type]->impressions ?? 0);

                return [
                    ...$this->metaPurchases($metrics[$type] ?? null),
                    'type'        => $type,
                    'label'       => TrafficSourcesTypeEnum::labels()[$type] ?? $type,
                    'spend'       => round($spend, 2),
                    'revenue'     => round($revenue, 2),
                    'orders'      => round($orders, 2),
                    'roas'        => $spend > 0 ? round($revenue / $spend, 2) : null,
                    'impressions' => $shown,
                    'clicks'      => $clicks,
                    'ctr'         => $this->rate($clicks, $shown),
                ];
            })
            ->filter(fn (array $channel) => $channel['spend'] > 0 || $channel['revenue'] > 0 || $channel['impressions'] > 0)
            ->values()
            ->all();
    }

    /**
     * Meta reports purchase value and cost in each ad account's own currency, and a group or an
     * organisation can hold accounts in several. Rows already in the parent's currency are summed
     * as they are; the others are summed per day and converted at that day's rate, as spend is.
     *
     * @param array<int, string> $adTypes
     *
     * @return Collection<int|string, object{impressions: int, clicks: int, meta_purchases: float, meta_value: float, meta_cost: float, meta_registrations: float, meta_registration_cost: float}>
     */
    private function adMetrics(Currency $currency, Collection $shops, array $adTypes, ?Carbon $from, ?Carbon $to, string $groupBy): Collection
    {
        $sourceCurrencies = [];
        $foreignDate      = 'CASE WHEN m.source_currency_id IS NULL OR m.source_currency_id = ? THEN NULL ELSE m.date END';

        return DB::table('traffic_source_campaign_metrics as m')
            ->join('traffic_source_campaigns as tc', 'tc.id', '=', 'm.traffic_source_campaign_id')
            ->join('traffic_sources as ts', 'ts.id', '=', 'tc.traffic_source_id')
            ->whereIn('ts.shop_id', $shops->pluck('id'))
            ->whereIn('ts.type', $adTypes)
            ->when($from, fn (Builder $query) => $query->where('m.date', '>=', $from->toDateString()))
            ->when($to, fn (Builder $query) => $query->where('m.date', '<=', $to->toDateString()))
            ->groupByRaw('1, 2, 3')
            ->selectRaw("$groupBy as bucket, m.source_currency_id, $foreignDate as rate_date, SUM(m.impressions) as impressions, SUM(m.clicks) as clicks, SUM(m.conversions) as meta_purchases, SUM(m.source_conversions_value) as meta_value, SUM(m.source_cost) as meta_cost, SUM(m.registrations) as meta_registrations, SUM(CASE WHEN m.registrations IS NOT NULL THEN m.source_cost ELSE 0 END) as meta_registration_cost", [$currency->id])
            ->get()
            ->each(function (object $row) use ($currency, &$sourceCurrencies) {
                $rate = $row->rate_date === null
                    ? 1.0
                    : (float) GetHistoricCurrencyExchange::run($sourceCurrencies[$row->source_currency_id] ??= Currency::find($row->source_currency_id), $currency, Carbon::parse($row->rate_date));

                $row->meta_value = $row->meta_value * $rate;
                $row->meta_cost  = $row->meta_cost * $rate;

                $row->meta_registration_cost = $row->meta_registration_cost * $rate;
            })
            ->groupBy('bucket')
            ->map(function (Collection $rows) {
                return (object) [
                    'impressions'    => (int) $rows->sum('impressions'),
                    'clicks'         => (int) $rows->sum('clicks'),
                    'meta_purchases'     => (float) $rows->sum('meta_purchases'),
                    'meta_value'         => (float) $rows->sum('meta_value'),
                    'meta_cost'          => (float) $rows->sum('meta_cost'),
                    'meta_registrations' => (float) $rows->sum('meta_registrations'),

                    'meta_registration_cost' => (float) $rows->sum('meta_registration_cost'),
                ];
            });
    }

    /**
     * @param array<int, string> $adTypes
     *
     * @return array<int, array<string, mixed>>
     */
    private function paidCampaigns(Organisation|Group $parent, Collection $shops, array $adTypes, ?Carbon $from, ?Carbon $to, string $costColumn, string $revenueColumn): array
    {
        $attributionFrom = $this->clipToAttributionStart($from);
        $spend           = $this->spendByType($shops, $from, $to, $costColumn, 'c.traffic_source_campaign_id');
        $revenue         = $this->revenueByType($shops, $attributionFrom, $to, $revenueColumn, 'p.traffic_source_campaign_id');
        $orders          = $this->ordersByType($shops, $attributionFrom, $to, 'p.traffic_source_campaign_id');
        $metrics = $this->adMetrics($parent->currency, $shops, $adTypes, $from, $to, 'tc.id');
        $isGroup = $parent instanceof Group;

        return DB::table('traffic_source_campaigns as tc')
            ->join('traffic_sources as ts', 'ts.id', '=', 'tc.traffic_source_id')
            ->join('shops as s', 's.id', '=', 'ts.shop_id')
            ->join('organisations as o', 'o.id', '=', 's.organisation_id')
            ->whereIn('ts.shop_id', $shops->pluck('id'))
            ->whereIn('ts.type', $adTypes)
            ->select('tc.id', 'tc.name', 'tc.reference', 'ts.type', 'ts.shop_id', 's.name as shop_name', 'o.name as organisation_name')
            ->get()
            ->map(function ($campaign) use ($spend, $revenue, $orders, $metrics, $isGroup) {
                $spent     = round((float) ($spend[$campaign->id] ?? 0), 2);
                $earned    = round((float) ($revenue[$campaign->id] ?? 0), 2);
                $purchases = round((float) ($orders[$campaign->id] ?? 0), 2);
                $clicks    = (int) ($metrics[$campaign->id]->clicks ?? 0);
                $shown     = (int) ($metrics[$campaign->id]->impressions ?? 0);

                return [
                    ...$this->metaPurchases($metrics[$campaign->id] ?? null),
                    'key'           => $campaign->shop_id.'|'.($campaign->reference !== null ? Str::after($campaign->reference, TrafficSourcesTypeEnum::INSTAGRAM_CAMPAIGN_PREFIX) : 'id'.$campaign->id),
                    'name'          => $campaign->name,
                    'channel'       => TrafficSourcesTypeEnum::labels()[$campaign->type] ?? $campaign->type,
                    'owner'         => $isGroup ? $campaign->organisation_name.' · '.$campaign->shop_name : $campaign->shop_name,
                    'spend'         => $spent,
                    'revenue'       => $earned,
                    'orders'        => $purchases,
                    'impressions'   => $shown,
                    'clicks'        => $clicks,
                    'ctr'           => $this->rate($clicks, $shown),
                    'cpa'           => $purchases > 0 ? round($spent / $purchases, 2) : null,
                    'roas'          => $spent > 0 ? round($earned / $spent, 2) : null,
                ];
            })
            ->filter(fn (array $campaign) => $campaign['spend'] > 0 || $campaign['revenue'] > 0)
            ->groupBy('key')
            ->map(fn (Collection $placements) => $this->wholeCampaign($placements))
            ->sortByDesc('spend')
            ->values()
            ->all();
    }

    /**
     * One Meta campaign runs on Facebook and on Instagram, and its spend is kept per placement. Ads
     * Manager shows the campaign as one row, so the placements are added up here, the rates taken
     * from the sums, and each placement's spend kept beside it.
     *
     * @param Collection<int, array<string, mixed>> $placements
     *
     * @return array<string, mixed>
     */
    private function wholeCampaign(Collection $placements): array
    {
        $sum = fn (string $key) => round((float) $placements->sum($key), 2);

        $spent       = $sum('spend');
        $earned      = $sum('revenue');
        $orders      = $sum('orders');
        $clicks      = (int) $placements->sum('clicks');
        $impressions = (int) $placements->sum('impressions');
        $metaValue   = $sum('meta_value');
        $metaCost    = $sum('meta_cost');

        return [
            'name'           => $placements->pluck('name')->first(fn ($name) => !is_numeric($name)) ?? $placements->first()['name'],
            'channel'        => $placements->pluck('channel')->unique()->implode(' + '),
            'placements'     => $placements->sortByDesc('spend')->map(fn (array $placement) => ['channel' => $placement['channel'], 'spend' => $placement['spend']])->values()->all(),
            'owner'          => $placements->first()['owner'],
            'spend'          => $spent,
            'revenue'        => $earned,
            'orders'         => $orders,
            'impressions'    => $impressions,
            'clicks'         => $clicks,
            'ctr'            => $this->rate($clicks, $impressions),
            'cpa'            => $orders > 0 ? round($spent / $orders, 2) : null,
            'roas'           => $spent > 0 ? round($earned / $spent, 2) : null,
            'meta_purchases' => $sum('meta_purchases'),
            'meta_value'     => $metaValue,
            'meta_cost'      => $metaCost,
            'meta_roas'      => $metaCost > 0 ? round($metaValue / $metaCost, 2) : null,
            'meta_registrations'         => $registrations = $sum('meta_registrations'),
            'meta_registration_cost'     => $registrationCost = $sum('meta_registration_cost'),
            'meta_cost_per_registration' => $registrations > 0 ? round($registrationCost / $registrations, 2) : null,
        ];
    }

    /**
     * A compared period is cut into the buckets of the current one, so the two lines sit point for
     * point, the way Search Console lays a comparison over the period.
     *
     * @param Collection<int, Shop> $shops
     *
     * @return array{bucket: string, points: array<int, array{date: string, posts: int, reach: int, engagements: int, views: int, organic_views: int, ad_views: int, interactions: int, followers: int|null}>}
     */
    private function trend(Collection $shops, ?SocialMediaPlatformEnum $platform, ?Carbon $from, ?Carbon $to, ?string $bucket = null): array
    {
        $days   = $from ? $from->diffInDays($to ?? now()) : PHP_INT_MAX;
        $bucket ??= match (true) {
            $days <= 62  => 'day',
            $days <= 400 => 'week',
            default      => 'month',
        };

        $posts = $this->posts($shops, $platform, $from, $to)
            ->selectRaw("date_trunc('$bucket', p.published_at)::date as bucket, COUNT(*) as posts, SUM(p.reach) as reach, SUM(".self::ENGAGEMENTS.') as engagements')
            ->groupBy('bucket')
            ->get()
            ->keyBy(fn ($row) => (string) $row->bucket);

        $withHistory = DB::table('social_media_account_daily_stats')
            ->whereIn('social_media_account_id', $this->accountsQuery($shops, $platform)->pluck('a.id'))
            ->whereNotNull('followers')
            ->when($from, fn (Builder $query) => $query->where('date', '>=', $from->toDateString()))
            ->when($to, fn (Builder $query) => $query->where('date', '<=', $to->toDateString()))
            ->groupBy('social_media_account_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('social_media_account_id');

        $followers = DB::query()
            ->fromSub(
                DB::table('social_media_account_daily_stats')
                    ->whereIn('social_media_account_id', $withHistory)
                    ->whereNotNull('followers')
                    ->when($from, fn (Builder $query) => $query->where('date', '>=', $from->toDateString()))
                    ->when($to, fn (Builder $query) => $query->where('date', '<=', $to->toDateString()))
                    ->selectRaw("DISTINCT ON (social_media_account_id, date_trunc('$bucket', date)) social_media_account_id, date_trunc('$bucket', date)::date as bucket, followers")
                    ->orderBy('social_media_account_id')
                    ->orderByRaw("date_trunc('$bucket', date)")
                    ->orderByDesc('date'),
                'last_in_bucket'
            )
            ->groupBy('bucket')
            ->selectRaw('bucket, SUM(followers) as followers')
            ->pluck('followers', 'bucket')
            ->mapWithKeys(fn ($value, $key) => [(string) $key => (int) $value]);

        $views        = $this->bucketDailyOrPosts($shops, $platform, $from, $to, $bucket, 'impressions', 'p.impressions');
        $split        = $this->viewsSplit($this->accountsQuery($shops, $platform)->pluck('a.id'), $from, $to, $bucket);
        $interactions = $this->bucketDailyOrPosts($shops, $platform, $from, $to, $bucket, 'engagements', self::ENGAGEMENTS);

        $dates = $posts->keys()->merge($followers->keys())->merge($views->keys())->merge($interactions->keys())->unique()->sort()->values();

        if ($from && $bucket === 'day') {
            $dates = collect(range(0, (int) $from->copy()->startOfDay()->diffInDays(($to ?? now())->copy()->startOfDay())))
                ->map(fn (int $offset) => $from->copy()->addDays($offset)->toDateString());
        }

        return [
            'bucket' => $bucket,
            'points' => $dates->map(fn (string $date) => [
                'date'         => $date,
                'posts'        => (int) ($posts[$date]->posts ?? 0),
                'reach'        => (int) ($posts[$date]->reach ?? 0),
                'engagements'  => (int) ($posts[$date]->engagements ?? 0),
                'views'         => $views[$date] ?? 0,
                'organic_views' => $split[$date]->organic ?? 0,
                'ad_views'      => $split[$date]->ads ?? 0,
                'interactions'  => $interactions[$date] ?? 0,
                'followers'    => $followers[$date] ?? null,
            ])->values()->all(),
        ];
    }

    /**
     * @param Collection<int, Shop> $shops
     *
     * @return Collection<string, int> bucket date => the accounts' daily figure where they have one, their posts' where not
     */
    private function bucketDailyOrPosts(Collection $shops, ?SocialMediaPlatformEnum $platform, ?Carbon $from, ?Carbon $to, string $bucket, string $dailyColumn, string $postExpression): Collection
    {
        $dailyAccounts = $this->dailyByAccount($this->accountsQuery($shops, $platform)->pluck('a.id'), $from, $to, $dailyColumn)->keys()->all();

        $daily = DB::table('social_media_account_daily_stats')
            ->whereIn('social_media_account_id', $dailyAccounts)
            ->whereNotNull($dailyColumn)
            ->when($from, fn (Builder $query) => $query->where('date', '>=', $from->toDateString()))
            ->when($to, fn (Builder $query) => $query->where('date', '<=', $to->toDateString()))
            ->groupByRaw("date_trunc('$bucket', date)")
            ->selectRaw("date_trunc('$bucket', date)::date as bucket, SUM($dailyColumn) as total")
            ->pluck('total', 'bucket');

        $fromPosts = $this->posts($shops, $platform, $from, $to)
            ->whereNotIn('a.id', $dailyAccounts)
            ->groupByRaw("date_trunc('$bucket', p.published_at)")
            ->selectRaw("date_trunc('$bucket', p.published_at)::date as bucket, SUM($postExpression) as total")
            ->pluck('total', 'bucket');

        return $daily->keys()->merge($fromPosts->keys())->unique()
            ->mapWithKeys(fn ($date) => [(string) $date => (int) ($daily[$date] ?? 0) + (int) ($fromPosts[$date] ?? 0)]);
    }

    /**
     * @param Collection<int, Shop> $shops
     *
     * @return array<int, array<string, mixed>>
     */
    private function platforms(Collection $shops, ?SocialMediaPlatformEnum $platform, ?Carbon $from, ?Carbon $to): array
    {
        $rows = $this->posts($shops, $platform, $from, $to)
            ->groupBy('a.platform')
            ->selectRaw('a.platform, COUNT(*) as posts, SUM(p.reach) as reach, SUM('.self::ENGAGEMENTS.') as engagements')
            ->get();

        $followers = $this->accountsQuery($shops, $platform)
            ->groupBy('a.platform')
            ->selectRaw('a.platform, SUM(a.followers) as followers')
            ->pluck('followers', 'platform');

        $rows  = $rows->keyBy('platform');
        $views = $followers->keys()->merge($rows->keys())->unique()
            ->mapWithKeys(fn (string $value) => [$value => $this->dailyOrPosts($shops, SocialMediaPlatformEnum::from($value), $from, $to, 'impressions', 'p.impressions')]);

        $totalReach = (int) $rows->sum('reach');
        $totalViews = (int) $views->sum();
        $split      = $views->keys()->mapWithKeys(fn (string $value) => [$value => $this->viewsSplit($this->accountsQuery($shops, SocialMediaPlatformEnum::from($value))->pluck('a.id'), $from, $to)->first()]);

        return $views->keys()->map(function (string $value) use ($rows, $views, $followers, $totalReach, $totalViews, $split) {
            $row = $rows[$value] ?? null;

            return [
                'platform'        => $value,
                'label'           => SocialMediaPlatformEnum::labels()[$value] ?? $value,
                'posts'           => (int) ($row->posts ?? 0),
                'reach'           => (int) ($row->reach ?? 0),
                'share'           => $this->rate((int) ($row->reach ?? 0), $totalReach),
                'views'           => $views[$value],
                'views_share'     => $this->rate($views[$value], $totalViews),
                'organic_views'   => (int) ($split[$value]->organic ?? 0),
                'ad_views'        => (int) ($split[$value]->ads ?? 0),
                'engagements'     => (int) ($row->engagements ?? 0),
                'engagement_rate' => $this->rate((int) ($row->engagements ?? 0), (int) ($row->reach ?? 0)),
                'followers'       => isset($followers[$value]) ? (int) $followers[$value] : null,
            ];
        })
            ->filter(fn (array $row) => $row['posts'] > 0 || $row['views'] > 0)
            ->sortByDesc(fn (array $row) => [$row['reach'], $row['views']])
            ->values()
            ->all();
    }

    /**
     * @param Collection<int, Shop> $shops
     *
     * @return array<int, array<string, mixed>>
     */
    private function formats(Collection $shops, ?SocialMediaPlatformEnum $platform, ?Carbon $from, ?Carbon $to): array
    {
        return $this->posts($shops, $platform, $from, $to)
            ->groupBy('p.type')
            ->selectRaw('p.type, COUNT(*) as posts, SUM(p.reach) as reach, SUM('.self::ENGAGEMENTS.') as engagements, SUM(p.saves) as saves, SUM(p.shares) as shares')
            ->get()
            ->map(fn ($row) => [
                'type'            => $row->type,
                'label'           => SocialMediaPostTypeEnum::labels()[$row->type] ?? $row->type,
                'posts'           => (int) $row->posts,
                'reach'           => (int) $row->reach,
                'average_reach'   => (int) round($row->reach / max(1, $row->posts)),
                'engagements'     => (int) $row->engagements,
                'saves'           => (int) $row->saves,
                'shares'          => (int) $row->shares,
                'engagement_rate' => $this->rate((int) $row->engagements, (int) $row->reach),
            ])
            ->sortByDesc('engagement_rate')
            ->values()
            ->all();
    }

    /**
     * The leading posts by each figure the dashboard ranks them by, engagements, views and viewers,
     * so a post many saw but few engaged with is still there when the ranking is by views.
     *
     * @param Collection<int, Shop> $shops
     *
     * @return array<int, array<string, mixed>>
     */
    private function topPosts(Collection $shops, ?SocialMediaPlatformEnum $platform, ?Carbon $from, ?Carbon $to): array
    {
        $postIds = collect([self::ENGAGEMENTS, 'p.impressions', 'p.reach'])
            ->flatMap(fn (string $figure) => $this->posts($shops, $platform, $from, $to)
                ->orderByRaw($figure.' DESC')
                ->orderByDesc('p.reach')
                ->limit(self::TOP_POSTS)
                ->pluck('p.id'))
            ->unique()
            ->values();

        return $this->posts($shops, $platform, $from, $to)
            ->whereIn('p.id', $postIds)
            ->join('shops as s', 's.id', '=', 'a.shop_id')
            ->selectRaw('p.id, p.type, p.description, p.permalink, p.published_at, p.reach, p.impressions, p.saves, p.shares, p.comments, p.reactions, p.link_clicks, p.is_boosted, p.ad_views, p.ad_reach, p.ad_link_clicks, '.self::ENGAGEMENTS.' as engagements, a.name as account_name, a.platform, s.name as shop_name')
            ->orderByRaw(self::ENGAGEMENTS.' DESC')
            ->orderByDesc('p.reach')
            ->get()
            ->map(fn ($post) => [
                'id'              => $post->id,
                'description'     => $post->description ? mb_strimwidth($post->description, 0, 120, '…') : null,
                'permalink'       => $post->permalink,
                'published_at'    => Carbon::parse($post->published_at)->toIso8601String(),
                'type'            => $post->type,
                'type_label'      => SocialMediaPostTypeEnum::labels()[$post->type] ?? $post->type,
                'platform'        => $post->platform,
                'platform_label'  => SocialMediaPlatformEnum::labels()[$post->platform] ?? $post->platform,
                'account_name'    => $post->account_name,
                'shop_name'       => $post->shop_name,
                'reach'           => (int) $post->reach,
                'impressions'     => (int) $post->impressions,
                'reactions'       => (int) $post->reactions,
                'comments'        => (int) $post->comments,
                'shares'          => (int) $post->shares,
                'saves'           => (int) $post->saves,
                'link_clicks'     => (int) $post->link_clicks,
                'engagements'     => (int) $post->engagements,
                'engagement_rate' => $this->rate((int) $post->engagements, (int) $post->reach),
                'is_boosted'      => (bool) $post->is_boosted,
                'ad_views'        => $post->ad_views === null ? null : (int) $post->ad_views,
                'ad_reach'        => $post->ad_reach === null ? null : (int) $post->ad_reach,
                'ad_link_clicks'  => $post->ad_link_clicks === null ? null : (int) $post->ad_link_clicks,
            ])
            ->all();
    }

    /**
     * @param Collection<int, Shop> $shops
     *
     * @return array<int, array<string, mixed>>
     */
    private function accounts(Collection $shops, ?SocialMediaPlatformEnum $platform, ?Carbon $from, ?Carbon $to): array
    {
        $posts = $this->posts($shops, $platform, $from, $to)
            ->groupBy('a.id')
            ->selectRaw('a.id, COUNT(*) as posts, SUM(p.reach) as reach, SUM(p.impressions) as impressions, SUM('.self::ENGAGEMENTS.') as engagements, SUM(p.link_clicks) as link_clicks')
            ->get()
            ->keyBy('id');

        $accountIds   = $this->accountsQuery($shops, $platform)->pluck('a.id');
        $dailyViews   = $this->dailyByAccount($accountIds, $from, $to, 'impressions');
        $dailyActions = $this->dailyByAccount($accountIds, $from, $to, 'engagements');
        $dailyClicks  = $this->dailyByAccount($accountIds, $from, $to, 'link_clicks');
        $viewsSplit   = $this->viewsSplit($accountIds, $from, $to, null, 'social_media_account_id');

        $shopsById = $shops->keyBy('id');

        return $this->accountsQuery($shops, $platform)
            ->select('a.id', 'a.name', 'a.username', 'a.platform', 'a.shop_id', 'a.followers', 'a.followers_at')
            ->get()
            ->map(function ($account) use ($posts, $shopsById, $dailyViews, $dailyActions, $dailyClicks, $viewsSplit) {
                $row          = $posts[$account->id] ?? null;
                $shop         = $shopsById[$account->shop_id];
                $views        = $dailyViews[$account->id] ?? (int) ($row->impressions ?? 0);
                $interactions = $dailyActions[$account->id] ?? (int) ($row->engagements ?? 0);

                return [
                    'id'              => $account->id,
                    'name'            => $account->name,
                    'username'        => $account->username,
                    'platform'        => $account->platform,
                    'platform_label'  => SocialMediaPlatformEnum::labels()[$account->platform] ?? $account->platform,
                    'shop_name'       => $shop->name,
                    'shop_slug'       => $shop->slug,
                    'country_code'    => $shop->country?->code,
                    'followers'       => $account->followers !== null ? (int) $account->followers : null,
                    'followers_at'    => $account->followers_at,
                    'posts'           => (int) ($row->posts ?? 0),
                    'reach'           => (int) ($row->reach ?? 0),
                    'engagements'     => (int) ($row->engagements ?? 0),
                    'link_clicks'     => $dailyClicks[$account->id] ?? (int) ($row->link_clicks ?? 0),
                    'engagement_rate' => $this->rate((int) ($row->engagements ?? 0), (int) ($row->reach ?? 0)),
                    'views'           => $views,
                    'organic_views'   => (int) ($viewsSplit[(string) $account->id]->organic ?? 0),
                    'ad_views'        => (int) ($viewsSplit[(string) $account->id]->ads ?? 0),
                    'interactions'    => $interactions,
                    'interaction_rate' => $this->rate($interactions, $views),
                ];
            })
            ->sortByDesc(fn (array $account) => [$account['views'], $account['reach']])
            ->values()
            ->all();
    }

    /**
     * @param Collection<int, Shop> $shops
     *
     * @return array{shops: array<int, array{slug: string, name: string, country_code: string|null, has_accounts: bool}>, countries: array<int, array{code: string, name: string}>, platforms: array<int, array{value: string, label: string}>}
     */
    private function options(Collection $shops): array
    {
        $withAccounts = DB::table('social_media_accounts')->whereIn('shop_id', $shops->pluck('id'))->distinct()->pluck('shop_id')->flip();

        return [
            'shops'     => $shops->map(fn (Shop $shop) => [
                'id'           => $shop->id,
                'slug'         => $shop->slug,
                'name'         => $shop->name,
                'country_code' => $shop->country?->code,
                'has_accounts' => isset($withAccounts[$shop->id]),
            ])->values()->all(),
            'countries' => $shops->pluck('country')->filter()->unique('id')->sortBy('name')
                ->map(fn ($country) => ['code' => $country->code, 'name' => $country->name])->values()->all(),
            'platforms' => collect(SocialMediaPlatformEnum::labels())
                ->except(SocialMediaPlatformEnum::TIKTOK->value)
                ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])->values()->all(),
        ];
    }

    /**
     * @param Collection<int, Shop> $shops
     */
    private function dataUntil(Collection $shops): ?string
    {
        $accountIds = DB::table('social_media_accounts')->whereIn('shop_id', $shops->pluck('id'))->pluck('id');

        $latest = collect([
            DB::table('social_media_posts')->whereIn('social_media_account_id', $accountIds)->max('published_at'),
            DB::table('social_media_account_daily_stats')->whereIn('social_media_account_id', $accountIds)->max('date'),
        ])->filter()->map(fn ($date) => Carbon::parse($date))->max();

        return $latest?->toDateString();
    }
}
