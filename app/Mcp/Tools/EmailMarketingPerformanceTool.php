<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 23 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Actions\CRM\TrafficSource\GetShopEmailMarketingPerformance;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('What a shop\'s newsletters and marketing mailshots actually earned: per mailshot the attributed revenue, attributed customers, prospects who registered, estimated sending cost, plus sent/opened/clicked/unsubscribed, with period totals. Use it for: which newsletter made the most sales, is emailing paying for itself, return per mailshot. For open and click rates alone MailshotPerformanceTool is enough. Revenue is attributed to the mailshot click before the order, by attribution share, credited by ORDER date.')]
#[IsReadOnly]
class EmailMarketingPerformanceTool extends AikuTool
{
    use WithMarketingScope;

    public function handle(Request $request): Response
    {
        $request->validate([
            'shop'  => ['required', 'string'],
            'from'  => ['nullable', 'date'],
            'to'    => ['nullable', 'date', 'after_or_equal:from'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'sort'  => ['nullable', 'in:recent,revenue'],
        ]);

        $shop = $this->authorisedShop($request);

        if (!$shop) {
            return $this->shopNotFoundError($request);
        }

        $period = $this->marketingPeriod($request);
        $limit  = $request->integer('limit', 10);
        $sort   = $request->string('sort', 'revenue')->toString() ?: 'revenue';

        $performance = GetShopEmailMarketingPerformance::run($shop, $period['from'], $period['to'], $sort === 'revenue' ? 200 : $limit);

        $mailshots = collect($performance['mailshots'])
            ->when($sort === 'revenue', fn ($collection) => $collection->sortByDesc('attributed_revenue'))
            ->take($limit)
            ->values()
            ->all();

        return Response::json([
            'shop'        => $shop->name,
            'currency'    => $shop->currency->code,
            'from'        => $period['from']->toDateString(),
            'to'          => $period['to']->toDateString(),
            'how_to_read' => [
                'attributed_revenue is invoiced net revenue of orders placed after a click on that mailshot, split by attribution share, credited by ORDER date within the attribution window.',
                'estimated_cost is our estimate of what sending the emails cost; nobody invoices it.',
                'Newsletters and marketing mailshots are separate channels: a newsletter is the regular send, a marketing mailshot is a promotional one.',
            ],
            'totals'      => $performance['totals'],
            'mailshots'   => $mailshots,
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'shop'  => $schema->string()->description('Shop slug or code, e.g. eu or EU')->required(),
            'from'  => $schema->string()->description('Start date (Y-m-d) of sending, default 30 days ago')->nullable(),
            'to'    => $schema->string()->description('End date (Y-m-d) inclusive, default today')->nullable(),
            'limit' => $schema->integer()->description('Maximum mailshots to return, default 10, max 50')->default(10),
            'sort'  => $schema->string()->description('revenue (default, best earners first) or recent')->default('revenue'),
        ];
    }
}
