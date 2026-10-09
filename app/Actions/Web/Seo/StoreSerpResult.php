<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo;

use App\Actions\Web\Webpage\WithWebpageIdsByPath;
use App\Models\Web\SeoCompetitor;
use App\Models\Web\SeoCompetitorRanking;
use App\Models\Web\SeoKeywordRanking;
use App\Models\Web\SeoTrackedKeyword;
use App\Models\Web\Website;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Reads one DataForSEO Google result page (advanced) into our position, the competitors' positions
 * and the SERP features of the day, and keeps the latest check on the tracked keyword.
 */
class StoreSerpResult
{
    use AsObject;
    use WithWebpageIdsByPath;

    /**
     * @var array<int, array<string, int>>
     */
    private array $webpageIdsByPathPerWebsite = [];

    public function handle(SeoTrackedKeyword $trackedKeyword, array $result): SeoKeywordRanking
    {
        $website = $trackedKeyword->shop->website;
        $date    = Carbon::parse(Arr::get($result, 'datetime') ?? now())->toDateString();
        $items   = collect(Arr::get($result, 'items') ?? []);
        $organic = $items->where('type', 'organic');

        $ours = $website ? $organic->first(fn (array $item) => self::isDomain(Arr::get($item, 'domain'), $website->domain)) : null;

        $ranking = SeoKeywordRanking::updateOrCreate(
            ['tracked_keyword_id' => $trackedKeyword->id, 'date' => $date],
            [
                'position'       => $ours ? (int) Arr::get($ours, 'rank_group') : null,
                'ranking_url'    => $ours ? Arr::get($ours, 'url') : null,
                'webpage_id'     => $ours && $website ? $this->matchWebpageId((string) Arr::get($ours, 'url'), $this->webpageIds($website)) : null,
                'serp_features'  => array_values(array_diff(Arr::get($result, 'item_types') ?? $items->pluck('type')->unique()->all(), ['organic'])),
                'in_ai_overview' => $website && $this->isInAiOverview($items->where('type', 'ai_overview')->all(), $website->domain),
                'depth'          => PostSerpTasks::depth($trackedKeyword->frequency),
            ]
        );

        $this->storeCompetitors($trackedKeyword, $organic->all(), $date);
        $this->updateTrackedKeyword($trackedKeyword, $ranking);

        return $ranking;
    }

    public static function isDomain(?string $domain, ?string $ourDomain): bool
    {
        $domain    = self::normaliseDomain($domain);
        $ourDomain = self::normaliseDomain($ourDomain);

        return $domain !== '' && $ourDomain !== '' && ($domain === $ourDomain || Str::endsWith($domain, '.'.$ourDomain));
    }

    public static function normaliseDomain(?string $domain): string
    {
        $domain = Str::lower(trim((string) $domain));
        $domain = parse_url(Str::contains($domain, '://') ? $domain : "https://$domain", PHP_URL_HOST) ?: '';

        return Str::startsWith($domain, 'www.') ? Str::after($domain, 'www.') : $domain;
    }

    /**
     * @return array<string, int>
     */
    private function webpageIds(Website $website): array
    {
        return $this->webpageIdsByPathPerWebsite[$website->id] ??= $this->webpageIdsByPath($website);
    }

    private function isInAiOverview(array $aiOverviews, string $ourDomain): bool
    {
        foreach ($aiOverviews as $aiOverview) {
            foreach (Arr::get($aiOverview, 'references') ?? [] as $reference) {
                if (self::isDomain(Arr::get($reference, 'domain') ?? Arr::get($reference, 'url'), $ourDomain)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function storeCompetitors(SeoTrackedKeyword $trackedKeyword, array $organic, string $date): void
    {
        $now  = now();
        $rows = $trackedKeyword->shop->seoCompetitors->map(function (SeoCompetitor $competitor) use ($trackedKeyword, $organic, $date, $now) {
            $item = Arr::first($organic, fn (array $item) => self::isDomain(Arr::get($item, 'domain'), $competitor->domain));

            return [
                'tracked_keyword_id' => $trackedKeyword->id,
                'competitor_id'      => $competitor->id,
                'date'               => $date,
                'position'           => $item ? (int) Arr::get($item, 'rank_group') : null,
                'url'                => $item ? Arr::get($item, 'url') : null,
                'created_at'         => $now,
                'updated_at'         => $now,
            ];
        })->all();

        if ($rows) {
            SeoCompetitorRanking::upsert($rows, ['tracked_keyword_id', 'competitor_id', 'date'], ['position', 'url', 'updated_at']);
        }
    }

    private function updateTrackedKeyword(SeoTrackedKeyword $trackedKeyword, SeoKeywordRanking $ranking): void
    {
        $previous = SeoKeywordRanking::where('tracked_keyword_id', $trackedKeyword->id)
            ->where('date', '<', $ranking->date)
            ->orderByDesc('date')
            ->first();

        $trackedKeyword->update([
            'pending_task_id'        => null,
            'pending_task_posted_at' => null,
            'last_checked_at'        => $ranking->date,
            'position'               => $ranking->position,
            'previous_position'      => $previous?->position,
            'previous_checked_at'    => $previous?->date,
            'ranking_url'            => $ranking->ranking_url,
            'ranking_webpage_id'     => $ranking->webpage_id,
            'serp_features'          => $ranking->serp_features,
            'in_ai_overview'         => $ranking->in_ai_overview,
        ]);
    }
}
