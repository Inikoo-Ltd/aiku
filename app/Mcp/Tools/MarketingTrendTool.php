<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 23 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Week-by-week or month-by-month marketing series for a shop: ad spend, attributed revenue, attributed invoices and registrations per traffic channel group (paid, organic, email, ai, other) or per channel. Use it for trends over time: is SEO/organic growing, is Google Ads spend rising faster than its return, are AI assistants sending more customers month on month. Same attribution rules as MarketingPerformanceTool (revenue by ORDER date, split by attribution share).')]
#[IsReadOnly]
class MarketingTrendTool extends AikuTool
{
    use WithMarketingScope;

    public function handle(Request $request): Response
    {
        $request->validate([
            'shop'        => ['required', 'string'],
            'from'        => ['nullable', 'date'],
            'to'          => ['nullable', 'date', 'after_or_equal:from'],
            'granularity' => ['nullable', 'in:week,month'],
            'by'          => ['nullable', 'in:group,channel'],
            'group'       => ['nullable', 'in:paid,organic,email,ai,other'],
        ]);

        $shop = $this->authorisedShop($request);

        if (!$shop) {
            return $this->shopNotFoundError($request);
        }

        $granularity = $request->string('granularity', 'month')->toString() ?: 'month';
        $by          = $request->string('by', 'group')->toString() ?: 'group';
        $to          = $request->string('to')->isNotEmpty() ? $request->date('to')->endOfDay() : now()->endOfDay();
        $from        = $request->string('from')->isNotEmpty() ? $request->date('from')->startOfDay() : $to->copy()->subMonths(6)->startOfMonth();

        $rows = DB::table('marketing_channel_daily')
            ->where('shop_id', $shop->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw("date_trunc(?, date)::date as period, type, sum(cost) as cost, sum(revenue) as revenue, sum(invoices) as invoices, sum(registrations) as registrations", [$granularity])
            ->groupBy('period', 'type')
            ->orderBy('period')
            ->get();

        $groupFilter = $request->string('group')->toString();

        $series = $rows
            ->map(fn ($row) => [
                'period'        => $row->period,
                'type'          => $row->type,
                'group'         => TrafficSourcesTypeEnum::tryFrom($row->type)?->group()['key'] ?? 'other',
                'cost'          => (float) $row->cost,
                'revenue'       => (float) $row->revenue,
                'invoices'      => (float) $row->invoices,
                'registrations' => (float) $row->registrations,
            ])
            ->when($groupFilter, fn ($collection) => $collection->where('group', $groupFilter))
            ->groupBy(fn (array $row) => $row['period'].'|'.($by === 'channel' ? $row['type'] : $row['group']))
            ->map(function ($bucket) use ($by) {
                $cost    = round($bucket->sum('cost'), 2);
                $revenue = round($bucket->sum('revenue'), 2);

                return [
                    'period'        => $bucket->first()['period'],
                    $by === 'channel' ? 'channel' : 'group' => $by === 'channel' ? $bucket->first()['type'] : $bucket->first()['group'],
                    'cost'          => $cost,
                    'revenue'       => $revenue,
                    'roas'          => $cost > 0 ? round($revenue / $cost, 2) : null,
                    'invoices'      => round($bucket->sum('invoices'), 2),
                    'registrations' => round($bucket->sum('registrations'), 2),
                ];
            })
            ->sortBy(fn (array $row) => [$row['period'], $row['channel'] ?? $row['group']])
            ->values()
            ->all();

        return Response::json([
            'shop'        => $shop->name,
            'currency'    => $shop->currency->code,
            'from'        => $from->toDateString(),
            'to'          => $to->toDateString(),
            'granularity' => $granularity,
            'by'          => $by,
            'how_to_read' => [
                'cost is ad spend invoiced by the platform; estimated email sending cost is NOT included here (MarketingPerformanceTool has it).',
                'revenue and invoices are attributed by ORDER date and split by attribution share; registrations are attributed sign-ups on their registration date.',
                'Attribution recording started 7 Aug 2026; periods before that read zero. The current period is partial.',
                'Organic group = SEO. AI group = ChatGPT, Gemini, Copilot, Claude, Perplexity.',
            ],
            'series'      => $series,
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'shop'        => $schema->string()->description('Shop slug or code, e.g. eu or EU')->required(),
            'from'        => $schema->string()->description('Start date (Y-m-d), default 6 months ago')->nullable(),
            'to'          => $schema->string()->description('End date (Y-m-d) inclusive, default today')->nullable(),
            'granularity' => $schema->string()->description('week or month, default month')->default('month'),
            'by'          => $schema->string()->description('group (paid/organic/email/ai/other, default) or channel (individual traffic sources)')->default('group'),
            'group'       => $schema->string()->description('Only this channel group: paid, organic, email, ai or other')->nullable(),
        ];
    }
}
