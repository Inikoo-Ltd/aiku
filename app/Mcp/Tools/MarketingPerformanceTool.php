<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 23 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Actions\CRM\TrafficSource\GetAggregatedMarketingOverview;
use App\Actions\CRM\TrafficSource\GetAttributionWindow;
use App\Actions\CRM\TrafficSource\GetShopMarketingOverview;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Marketing return for a shop, an organisation or the whole group over a period, with the same figures for the equal-length period before it and the % change. Per traffic channel (Google Ads, Meta Ads, organic search, newsletters, marketing mailshots, AI assistants, referrals...) and per channel group (paid, organic, email, ai, other): ad spend, estimated email cost, attributed revenue, revenue still pending invoice, ROAS, customer acquisition cost, registrations, orders, visits, unsubscribes. Also top campaigns and top referring sites (search engines, AI assistants, directories). Use it for: is Google Ads working / paying back, am I overspending on ads, is SEO (organic) improving, which channel brings the customers, ROI of marketing. Revenue is attributed to the channels that touched the customer before the order, split by attribution share, credited by ORDER date inside the shop\'s attribution window. Ask MarketingTrendTool for a month-by-month series.')]
#[IsReadOnly]
class MarketingPerformanceTool extends AikuTool
{
    use WithMarketingScope;

    public function handle(Request $request): Response
    {
        $request->validate([
            'shop'         => ['nullable', 'string'],
            'organisation' => ['nullable', 'string'],
            'from'         => ['nullable', 'date'],
            'to'           => ['nullable', 'date', 'after_or_equal:from'],
            'limit'        => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $scope = $this->marketingScope($request);

        if ($scope instanceof Response) {
            return $scope;
        }

        $period  = $this->marketingPeriod($request);
        $limit   = $request->integer('limit', 10);
        $current = $this->overview($scope, $period['from'], $period['to']);
        $before  = $this->overview($scope, $period['previous_from'], $period['previous_to']);

        $channels = collect($current['channels'])->keyBy('type');
        $previous = collect($before['channels'])->keyBy('type');

        return Response::json([
            'scope'           => [
                'type'     => strtolower(class_basename($scope)),
                'name'     => $scope->name,
                'currency' => $current['currency_code'],
            ],
            'period'          => ['from' => $period['from']->toDateString(), 'to' => $period['to']->toDateString()],
            'previous_period' => ['from' => $period['previous_from']->toDateString(), 'to' => $period['previous_to']->toDateString()],
            'how_to_read'     => $this->howToRead($scope),
            'totals'          => $this->withComparison($current['totals'], $before['totals'], ['spend', 'spend_ads', 'spend_email', 'revenue', 'pending', 'registrations', 'orders', 'unsubscribed', 'roas', 'cac']),
            'baseline'        => [
                'current'  => $current['baseline'],
                'previous' => $before['baseline'],
                'meaning'  => 'All registrations/orders/revenue of the scope in the period, attributed or not. Attributed totals are the share of this that marketing can claim.',
            ],
            'channel_groups'  => $this->channelGroups($channels, $previous),
            'channels'        => $channels
                ->map(fn (array $channel) => $this->withComparison(
                    Arr::only($channel, ['name', 'type', 'group', 'spend', 'spend_is_estimated', 'revenue', 'pending', 'registrations', 'orders', 'visits', 'unsubscribed', 'roas']),
                    $previous->get($channel['type'], []),
                    ['spend', 'revenue', 'pending', 'registrations', 'orders', 'visits', 'unsubscribed', 'roas']
                ))
                ->values()
                ->all(),
            'campaigns'       => collect($current['campaigns'] ?? [])->take($limit)->values()->all(),
            'referrers'       => collect($current['referrers'] ?? [])->take($limit)->values()->all(),
            'shops'           => collect($current['children'] ?? [])
                ->map(fn (array $child) => Arr::except($child, ['route']))
                ->values()
                ->all(),
        ]);
    }

    private function overview(Shop|Organisation|Group $scope, Carbon $from, Carbon $to): array
    {
        return $scope instanceof Shop
            ? GetShopMarketingOverview::run($scope, $from, $to)
            : GetAggregatedMarketingOverview::run($scope, $from, $to);
    }

    /**
     * @param array<string, mixed> $current
     * @param array<string, mixed> $previous
     * @param array<int, string>   $metrics
     *
     * @return array<string, mixed>
     */
    private function withComparison(array $current, array $previous, array $metrics): array
    {
        $current['previous'] = Arr::only($previous, $metrics);
        $current['change_pct'] = collect($metrics)
            ->filter(fn (string $metric) => is_numeric($current[$metric] ?? null) && is_numeric($previous[$metric] ?? null))
            ->mapWithKeys(fn (string $metric) => [$metric => $this->percentChange((float) $current[$metric], (float) $previous[$metric])])
            ->all();

        return $current;
    }

    /**
     * @param \Illuminate\Support\Collection<string, array<string, mixed>> $channels
     * @param \Illuminate\Support\Collection<string, array<string, mixed>> $previous
     *
     * @return array<int, array<string, mixed>>
     */
    private function channelGroups($channels, $previous): array
    {
        $sum = fn ($rows) => [
            'spend'         => round($rows->sum('spend'), 2),
            'revenue'       => round($rows->sum('revenue'), 2),
            'pending'       => round($rows->sum('pending'), 2),
            'registrations' => round($rows->sum('registrations'), 2),
            'orders'        => round($rows->sum('orders'), 2),
            'visits'        => (int) $rows->sum('visits'),
        ];

        $previousByGroup = $previous->groupBy('group')->map($sum);

        return $channels
            ->groupBy('group')
            ->map(function ($rows, string $group) use ($sum, $previousByGroup) {
                $totals         = $sum($rows);
                $totals['roas'] = $totals['spend'] > 0 ? round($totals['revenue'] / $totals['spend'], 2) : null;

                return $this->withComparison(
                    ['group' => $group, 'label' => $rows->first()['group_label'] ?? $group, 'channels' => $rows->pluck('name')->values()->all()] + $totals,
                    ($previousByGroup[$group] ?? []) + ['roas' => (($previousByGroup[$group]['spend'] ?? 0) > 0) ? round($previousByGroup[$group]['revenue'] / $previousByGroup[$group]['spend'], 2) : null],
                    ['spend', 'revenue', 'pending', 'registrations', 'orders', 'visits', 'roas']
                );
            })
            ->sortBy(fn (array $group) => -max($group['spend'], $group['revenue']))
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function howToRead(Shop|Organisation|Group $scope): array
    {
        $window = $scope instanceof Shop ? GetAttributionWindow::run($scope) : null;

        return array_values(array_filter([
            'Revenue is invoiced net revenue attributed to the channels that touched the customer before the order, split by attribution share; shares of one customer sum to 1, so channel figures add up to the attributed total and never double count.',
            'Revenue is credited to the ORDER date, not the invoice date, and only for orders placed within the attribution window after the touch.',
            $window ? "Attribution window for this shop: {$window} days." : null,
            'ROAS = attributed revenue / spend. null means nothing was spent, or money was spent and orders are still pending invoice (not yet measurable).',
            'CAC = spend / attributed registrations. spend_ads is invoiced by ad platforms; spend_email is our estimate of sending cost.',
            'pending is revenue from attributed orders not yet invoiced. baseline is everything the scope did in the period; the gap to attributed is trade arriving with no recorded marketing touch.',
            'Organic group = SEO (Google, Bing, DuckDuckGo...). AI group = arrivals from ChatGPT, Gemini, Copilot, Claude, Perplexity. Attribution recording started 7 Aug 2026; earlier periods read zero.',
            'A change_pct of null means the previous period had zero for that metric.',
        ]));
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'shop'         => $schema->string()->description('Shop slug or code, e.g. eu or EU. Omit for an organisation or group roll-up.')->nullable(),
            'organisation' => $schema->string()->description('Organisation slug or code, for a roll-up of all its shops. Omit both shop and organisation for the whole group.')->nullable(),
            'from'         => $schema->string()->description('Start date (Y-m-d), default 30 days ago')->nullable(),
            'to'           => $schema->string()->description('End date (Y-m-d) inclusive, default today')->nullable(),
            'limit'        => $schema->integer()->description('Maximum campaigns and referrers to return, default 10, max 50')->default(10),
        ];
    }
}
