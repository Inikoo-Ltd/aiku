<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo\Json;

use App\Actions\OrgAction;
use App\Models\Web\SeoTrackedKeyword;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

/**
 * Every Google check of a tracked keyword, with the competitors' positions from the same checks and
 * the Search Console average position of the same query on each day, for the position chart.
 */
class GetSeoKeywordHistory extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo([
            "websites-view.{$this->shop->organisation_id}",
            "web.{$this->shop->id}",
            "web.{$this->shop->id}.view",
            'group-webmaster.view',
        ]);
    }

    /**
     * @return array{keyword: string, checks: array<int, array{date: string, position: int|null, depth: int, url: string|null}>, search_console: array<string, float>, competitors: array<int, array{domain: string, label: string|null, positions: array<string, int|null>}>}
     */
    public function handle(SeoTrackedKeyword $trackedKeyword): array
    {
        $checks = DB::table('seo_keyword_rankings')
            ->where('tracked_keyword_id', $trackedKeyword->id)
            ->orderBy('date')
            ->get(['date', 'position', 'depth', 'ranking_url']);

        $from = $checks->first()?->date;

        $searchConsole = $from && $trackedKeyword->shop->website ? DB::connection('aiku_no_sticky')->table('search_console_page_queries')
            ->where('website_id', $trackedKeyword->shop->website->id)
            ->whereRaw('lower(query) = ?', [$trackedKeyword->keyword])
            ->where('date', '>=', $from)
            ->groupBy('date')
            ->selectRaw('date, ROUND(SUM(position * impressions) / NULLIF(SUM(impressions), 0), 1) AS position')
            ->pluck('position', 'date')
            ->filter(fn ($position) => $position !== null)
            ->map(fn ($position) => (float) $position)
            ->all() : [];

        $competitors = DB::table('seo_competitor_rankings')
            ->join('seo_competitors', 'seo_competitors.id', '=', 'seo_competitor_rankings.competitor_id')
            ->where('seo_competitor_rankings.tracked_keyword_id', $trackedKeyword->id)
            ->orderBy('seo_competitor_rankings.date')
            ->get(['seo_competitors.domain', 'seo_competitors.label', 'seo_competitor_rankings.date', 'seo_competitor_rankings.position'])
            ->groupBy('domain')
            ->map(fn ($rows, $domain) => [
                'domain'    => $domain,
                'label'     => $rows->first()->label,
                'positions' => $rows->mapWithKeys(fn ($row) => [$row->date => $row->position])->all(),
            ])
            ->values()
            ->all();

        return [
            'keyword'        => $trackedKeyword->keyword,
            'checks'         => $checks->map(fn ($check) => [
                'date'     => $check->date,
                'position' => $check->position,
                'depth'    => (int) $check->depth,
                'url'      => $check->ranking_url,
            ])->all(),
            'search_console' => $searchConsole,
            'competitors'    => $competitors,
        ];
    }

    public function asController(SeoTrackedKeyword $seoTrackedKeyword, ActionRequest $request): array
    {
        $this->initialisationFromShop($seoTrackedKeyword->shop, $request);

        return $this->handle($seoTrackedKeyword);
    }
}
