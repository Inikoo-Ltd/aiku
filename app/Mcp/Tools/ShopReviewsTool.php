<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Enums\SysAdmin\Authorisation\ShopPermissionsEnum;
use App\Models\Reviews\Review;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Customer review sentiment for a shop: rating distribution, average rating, and the most recent review messages.')]
class ShopReviewsTool extends AikuTool
{
    protected function permission(): ShopPermissionsEnum
    {
        return ShopPermissionsEnum::CRM_VIEW;
    }

    public function handle(Request $request): Response
    {
        $request->validate([
            'shop'  => ['required', 'string'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $shop = $this->authorisedShop($request);
        if (!$shop) {
            return Response::error('Shop not found or permission denied.');
        }

        $stats = $shop->reviewStats;

        $recentReviews = Review::where('shop_id', $shop->id)
            ->whereNotNull('message')
            ->where('message', '!=', '')
            ->latest('id')
            ->limit($request->integer('limit', 10))
            ->get(['rating_main', 'message', 'review_status', 'created_at'])
            ->map(fn (Review $review) => [
                'rating'  => $review->rating_main,
                'message' => $review->message,
                'status'  => $review->review_status,
                'date'    => $review->created_at?->toDateString(),
            ]);

        return Response::json([
            'shop'           => $shop->name,
            'number_reviews' => (int) ($stats?->number_reviews ?? 0),
            'average_rating' => $stats?->average_rating_main !== null ? round((float) $stats->average_rating_main, 2) : null,
            'ratings'        => [
                '1' => (int) ($stats?->number_rating_1 ?? 0),
                '2' => (int) ($stats?->number_rating_2 ?? 0),
                '3' => (int) ($stats?->number_rating_3 ?? 0),
                '4' => (int) ($stats?->number_rating_4 ?? 0),
                '5' => (int) ($stats?->number_rating_5 ?? 0),
            ],
            'recent_reviews' => $recentReviews,
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'shop'  => $schema->string()->description('Shop slug')->required(),
            'limit' => $schema->integer()->description('Maximum recent review messages to return, default 10')->minimum(1)->maximum(50),
        ];
    }
}
