<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 23 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Actions\CRM\TrafficSource\GetShopOfferPerformance;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Whether a shop\'s offers (discounts, promotions) moved sales in a period: per offer the orders, customers, discount given, revenue, and the uptake among customers who were emailed about offers versus the rest (lift). Use it for: did the promotion work, which offers drive orders, does emailing offers change uptake.')]
#[IsReadOnly]
class OfferPerformanceTool extends AikuTool
{
    use WithMarketingScope;

    public function handle(Request $request): Response
    {
        $request->validate([
            'shop'  => ['required', 'string'],
            'from'  => ['nullable', 'date'],
            'to'    => ['nullable', 'date', 'after_or_equal:from'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $shop = $this->authorisedShop($request);

        if (!$shop) {
            return $this->shopNotFoundError($request);
        }

        $period      = $this->marketingPeriod($request);
        $performance = GetShopOfferPerformance::run($shop, $period['from'], $period['to']);

        return Response::json([
            'shop'        => $shop->name,
            'currency'    => $performance['currency_code'],
            'from'        => $period['from']->toDateString(),
            'to'          => $period['to']->toDateString(),
            'how_to_read' => [
                'uptake_emailed is the share of emailed customers who used the offer, uptake_rest the share of everybody else; lift is the difference in percentage points.',
                'discount is the money given away by the offer in the period; revenue is the net revenue of the orders that used it.',
            ],
            'reach'       => $performance['reach'],
            'offers'      => collect($performance['offers'])->take($request->integer('limit', 10))->values()->all(),
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'shop'  => $schema->string()->description('Shop slug or code, e.g. eu or EU')->required(),
            'from'  => $schema->string()->description('Start date (Y-m-d), default 30 days ago')->nullable(),
            'to'    => $schema->string()->description('End date (Y-m-d) inclusive, default today')->nullable(),
            'limit' => $schema->integer()->description('Maximum offers to return, default 10, max 50')->default(10),
        ];
    }
}
