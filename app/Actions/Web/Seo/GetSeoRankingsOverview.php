<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo;

use App\Models\Catalogue\Shop;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * The figures above the Rankings table: visibility, search intent overview and competitors.
 */
class GetSeoRankingsOverview
{
    use AsObject;

    /**
     * @return array{summary: array, intents: array, competitors: array}
     */
    public function handle(Shop $shop): array
    {
        return [
            'summary'     => $this->summary($shop),
            'intents'     => $this->intents($shop),
            'competitors' => $this->competitors($shop),
        ];
    }

    /**
     * Counts over the active tracked keywords at their latest check.
     */
    private function summary(Shop $shop): array
    {
        $summary = DB::table('seo_tracked_keywords')
            ->where('shop_id', $shop->id)
            ->where('is_active', true)
            ->selectRaw('COUNT(*) AS tracked')
            ->selectRaw('COUNT(last_checked_at) AS checked')
            ->selectRaw('COUNT(pending_task_id) AS pending')
            ->selectRaw('COUNT(*) FILTER (WHERE position <= 3) AS top_3')
            ->selectRaw('COUNT(*) FILTER (WHERE position <= 10) AS top_10')
            ->selectRaw('COUNT(*) FILTER (WHERE position <= ?) AS top_20', [PostSerpTasks::DAILY_DEPTH])
            ->selectRaw('COUNT(*) FILTER (WHERE in_ai_overview) AS in_ai_overview')
            ->selectRaw('COUNT(*) FILTER (WHERE previous_checked_at IS NOT NULL AND position IS NOT NULL AND (previous_position IS NULL OR position < previous_position)) AS improved')
            ->selectRaw('COUNT(*) FILTER (WHERE previous_checked_at IS NOT NULL AND previous_position IS NOT NULL AND (position IS NULL OR position > previous_position)) AS declined')
            ->selectRaw('MAX(last_checked_at) AS last_checked_at')
            ->first();

        return [
            'tracked'         => (int) $summary->tracked,
            'checked'         => (int) $summary->checked,
            'pending'         => (int) $summary->pending,
            'top_3'           => (int) $summary->top_3,
            'top_10'          => (int) $summary->top_10,
            'top_20'          => (int) $summary->top_20,
            'in_ai_overview'  => (int) $summary->in_ai_overview,
            'improved'        => (int) $summary->improved,
            'declined'        => (int) $summary->declined,
            'last_checked_at' => $summary->last_checked_at ? substr($summary->last_checked_at, 0, 10) : null,
        ];
    }

    /**
     * Share of checked keywords per intent, and how many of them we have in the top 10.
     */
    private function intents(Shop $shop): array
    {
        return DB::table('seo_tracked_keywords')
            ->leftJoin('seo_keywords', function ($join) {
                $join->on('seo_keywords.shop_id', '=', 'seo_tracked_keywords.shop_id')
                    ->on('seo_keywords.keyword', '=', 'seo_tracked_keywords.keyword')
                    ->on('seo_keywords.country_code', '=', 'seo_tracked_keywords.country_code')
                    ->on('seo_keywords.language_code', '=', 'seo_tracked_keywords.language_code');
            })
            ->where('seo_tracked_keywords.shop_id', $shop->id)
            ->where('seo_tracked_keywords.is_active', true)
            ->whereNotNull('seo_tracked_keywords.last_checked_at')
            ->groupBy('seo_keywords.intent')
            ->selectRaw('seo_keywords.intent')
            ->selectRaw('COUNT(*) AS keywords')
            ->selectRaw('COUNT(*) FILTER (WHERE seo_tracked_keywords.position <= 10) AS top_10')
            ->orderByDesc('keywords')
            ->get()
            ->map(fn ($row) => [
                'intent'   => $row->intent,
                'keywords' => (int) $row->keywords,
                'top_10'   => (int) $row->top_10,
            ])
            ->all();
    }

    /**
     * Each competitor's positions on our tracked keywords, from the same checks.
     */
    private function competitors(Shop $shop): array
    {
        return DB::table('seo_competitors')
            ->leftJoin('seo_competitor_rankings', 'seo_competitor_rankings.competitor_id', '=', 'seo_competitors.id')
            ->leftJoin('seo_tracked_keywords', function ($join) {
                $join->on('seo_tracked_keywords.id', '=', 'seo_competitor_rankings.tracked_keyword_id')
                    ->where('seo_tracked_keywords.is_active', true)
                    ->whereRaw('seo_competitor_rankings.date = seo_tracked_keywords.last_checked_at::date');
            })
            ->where('seo_competitors.shop_id', $shop->id)
            ->groupBy('seo_competitors.id', 'seo_competitors.domain', 'seo_competitors.label')
            ->select('seo_competitors.domain', 'seo_competitors.label')
            ->selectRaw('COUNT(seo_tracked_keywords.id) AS checked')
            ->selectRaw('COUNT(seo_tracked_keywords.id) FILTER (WHERE seo_competitor_rankings.position <= 3) AS top_3')
            ->selectRaw('COUNT(seo_tracked_keywords.id) FILTER (WHERE seo_competitor_rankings.position <= 10) AS top_10')
            ->orderByDesc('top_10')
            ->orderBy('seo_competitors.domain')
            ->get()
            ->map(fn ($row) => [
                'domain'  => $row->domain,
                'label'   => $row->label,
                'checked' => (int) $row->checked,
                'top_3'   => (int) $row->top_3,
                'top_10'  => (int) $row->top_10,
            ])
            ->all();
    }
}
