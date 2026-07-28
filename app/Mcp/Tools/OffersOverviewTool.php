<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Enums\SysAdmin\Authorisation\ShopPermissionsEnum;
use App\Models\Discounts\Offer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Offers for a shop with usage stats: which are currently active and which performed best (orders and revenue attributed).')]
#[IsReadOnly]
class OffersOverviewTool extends AikuTool
{
    protected function permission(): ShopPermissionsEnum
    {
        return ShopPermissionsEnum::DISCOUNTS_VIEW;
    }

    public function handle(Request $request): Response
    {
        $request->validate([
            'shop' => ['required', 'string'],
            'status' => ['string', 'in:active,all'],
        ]);

        $shop = $this->authorisedShop($request);
        if (!$shop) {
            return Response::error('No shop matching that slug is available to you. Call my-access-tool to list the shops you can query, then use the slug exactly as given there. Do not guess slugs.');
        }

        $status = (string) $request->string('status', 'active');

        $query = Offer::where('shop_id', $shop->id);

        if ($status === 'active') {
            $query->where('status', true);
        }

        $offers = $query
            ->leftJoin('offer_stats', 'offers.id', '=', 'offer_stats.offer_id')
            ->select(
                'offers.code',
                'offers.name',
                'offers.state',
                'offers.start_at',
                'offers.end_at',
                'offer_stats.number_orders',
                'offer_stats.number_customers',
                'offer_stats.amount'
            )
            ->orderByRaw('offer_stats.amount DESC NULLS LAST')
            ->limit(30)
            ->get()
            ->map(fn ($offer) => [
                'code' => $offer->code,
                'name' => $offer->name,
                'state' => $offer->state,
                'start_at' => $offer->start_at?->toDateTimeString(),
                'end_at' => $offer->end_at?->toDateTimeString(),
                'number_orders' => (int) ($offer->number_orders ?? 0),
                'number_customers' => (int) ($offer->number_customers ?? 0),
                'amount' => (float) ($offer->amount ?? 0),
            ])
            ->toArray();

        return Response::json([
            'shop' => $shop->name,
            'offers' => $offers,
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'shop' => $schema->string()->description('Shop slug')->required(),
            'status' => $schema->string()->description('Filter by status (active or all) - default active'),
        ];
    }
}
