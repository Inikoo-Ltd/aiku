<?php

/*
 * Created: Mon, 23 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026
 */

namespace App\Actions\CRM\TrafficSourceCampaign\GoogleAds;

use App\Actions\CRM\TrafficSource\Hydrator\TrafficSourceCampaignHydrateStats;
use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\TrafficSource;
use App\Models\CRM\TrafficSourceCampaign;
use App\Services\GoogleAds\GoogleAdsException;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * Folds the campaigns Aiku minted from a click URL's gad_campaignid, which Google does not know, into
 * the campaign Google says those clicks belong to.
 *
 * Performance Max clicks carry an id in their URL that is not the id of the campaign paying for them,
 * so every customer and order they brought sat on a campaign without spend. Google's click_view is
 * read back over the window, each such id is matched to the campaign its clicks resolve to, and when
 * one campaign clearly owns them the ghost is merged into it: the customer and order attribution
 * rows move across and the ghost is hidden from the listing. Anything less clear is reported and
 * left alone rather than guessed.
 */
class RepairGoogleAdsGhostCampaigns
{
    use AsAction;

    public const int MIN_RESOLVED_CLICKS = 10;

    public const float MIN_SHARE = 0.9;

    /**
     * The campaign that owns the clicks of a URL id, or null when too few of them resolved, no single
     * campaign holds MIN_SHARE of them, or they resolve to the URL id itself.
     *
     * @param array<string, int> $resolvedCounts Google campaign id => clicks
     */
    public static function owningCampaignRef(array $resolvedCounts, string $urlRef): ?string
    {
        $total = array_sum($resolvedCounts);

        if ($total < self::MIN_RESOLVED_CLICKS) {
            return null;
        }

        arsort($resolvedCounts);
        $ref    = (string) array_key_first($resolvedCounts);
        $clicks = $resolvedCounts[$ref];

        if ($ref === $urlRef || $clicks / $total < self::MIN_SHARE) {
            return null;
        }

        return $ref;
    }

    /**
     * @return array<int, array{shop: string, url_ref: string, resolved_clicks: int, campaign: string, share: string, customers: int, orders: int, result: string}>
     * @throws GoogleAdsException
     */
    public function handle(Shop $shop, int $days = 90, bool $dryRun = false): array
    {
        $fetch = FetchGoogleAdsClickCampaigns::make();
        $days  = $fetch->boundedDays($days);

        $campaignByClick = $fetch->read($fetch->clientFor($shop), $days);

        if (!$dryRun) {
            $fetch->store($shop, $campaignByClick, $days);
        }

        $trafficSource = TrafficSource::where('shop_id', $shop->id)
            ->where('type', TrafficSourcesTypeEnum::GOOGLE_ADS->value)
            ->firstOrFail();

        $campaigns = TrafficSourceCampaign::where('traffic_source_id', $trafficSource->id)->get()->keyBy('reference');
        $rows      = [];

        foreach ($this->resolvedCountsByUrlRef($shop, $campaignByClick, $days) as $urlRef => $resolvedCounts) {
            $ghost = $campaigns->get($urlRef);

            if (!$ghost || filled(data_get($ghost->data, 'status'))) {
                continue;
            }

            $total     = array_sum($resolvedCounts);
            $targetRef = self::owningCampaignRef($resolvedCounts, (string) $urlRef);
            $target    = $targetRef ? $campaigns->get($targetRef) : null;
            $moving    = $this->attributionCounts($ghost);

            $row = [
                'shop'            => $shop->slug,
                'url_ref'         => (string) $urlRef,
                'resolved_clicks' => $total,
                'campaign'        => $target ? "$target->name ($target->reference)" : ($targetRef ?? '-'),
                'share'           => $targetRef ? round(100 * $resolvedCounts[$targetRef] / $total).'%' : '-',
                'customers'       => $moving['Customer'] ?? 0,
                'orders'          => $moving['Order'] ?? 0,
            ];

            if (!$target) {
                $rows[] = [...$row, 'result' => $targetRef ? 'campaign not in Aiku yet, run google-ads:fetch-campaigns' : 'undecided'];

                continue;
            }

            if ($dryRun) {
                $rows[] = [...$row, 'result' => 'would merge'];

                continue;
            }

            try {
                $folded = $this->merge($ghost, $target);
                $rows[] = [...$row, 'result' => $folded > 0 ? "merged ($folded already on the campaign, folded in)" : 'merged'];
            } catch (Throwable $e) {
                report($e);
                $rows[] = [...$row, 'result' => 'failed: '.$e->getMessage()];
            }
        }

        return $rows;
    }

    /**
     * @param array<string, string> $campaignByClick
     * @return array<string, array<string, int>> URL campaign id => Google campaign id => clicks
     */
    private function resolvedCountsByUrlRef(Shop $shop, array $campaignByClick, int $days): array
    {
        $counts = [];

        $clicks = DB::table('traffic_source_clicks')
            ->where('shop_id', $shop->id)
            ->where('type', TrafficSourcesTypeEnum::GOOGLE_ADS->value)
            ->where('created_at', '>=', now()->subDays($days + 1))
            ->whereNotNull('campaign_ref')
            ->whereNotNull('click_id')
            ->select('click_id', 'campaign_ref')
            ->cursor();

        foreach ($clicks as $click) {
            $resolved = $campaignByClick[$click->click_id] ?? null;

            if ($resolved !== null) {
                $counts[$click->campaign_ref][$resolved] = ($counts[$click->campaign_ref][$resolved] ?? 0) + 1;
            }
        }

        return $counts;
    }

    /**
     * @return array<string, int> model type => attribution rows
     */
    private function attributionCounts(TrafficSourceCampaign $campaign): array
    {
        return DB::table('model_has_traffic_sources')
            ->where('traffic_source_campaign_id', $campaign->id)
            ->groupBy('model_type')
            ->selectRaw('model_type, COUNT(*) as total')
            ->pluck('total', 'model_type')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    public function merge(TrafficSourceCampaign $ghost, TrafficSourceCampaign $target): int
    {
        $folded = DB::transaction(function () use ($ghost, $target) {
            $ghost->update(['merged_into_id' => $target->id]);

            DB::statement(
                'UPDATE model_has_traffic_sources AS t
                SET share = t.share + g.share,
                    first_touch_at = LEAST(t.first_touch_at, g.first_touch_at),
                    last_touch_at = GREATEST(t.last_touch_at, g.last_touch_at),
                    updated_at = NOW()
                FROM model_has_traffic_sources AS g
                WHERE g.traffic_source_campaign_id = ?
                  AND t.traffic_source_campaign_id = ?
                  AND t.model_type = g.model_type
                  AND t.model_id = g.model_id
                  AND t.traffic_source_id = g.traffic_source_id',
                [$ghost->id, $target->id]
            );

            $folded = DB::delete(
                'DELETE FROM model_has_traffic_sources AS g
                WHERE g.traffic_source_campaign_id = ?
                  AND EXISTS (
                      SELECT 1 FROM model_has_traffic_sources AS t
                      WHERE t.traffic_source_campaign_id = ?
                        AND t.model_type = g.model_type
                        AND t.model_id = g.model_id
                        AND t.traffic_source_id = g.traffic_source_id
                  )',
                [$ghost->id, $target->id]
            );

            DB::table('model_has_traffic_sources')
                ->where('traffic_source_campaign_id', $ghost->id)
                ->update(['traffic_source_campaign_id' => $target->id]);

            TrafficSourceCampaign::where('merged_into_id', $ghost->id)->update(['merged_into_id' => $target->id]);

            return $folded;
        });

        TrafficSourceCampaignHydrateStats::dispatch($ghost);
        TrafficSourceCampaignHydrateStats::dispatch($target);

        return $folded;
    }

    public function getCommandSignature(): string
    {
        return 'google-ads:repair-ghost-campaigns
            {shop? : Shop slug, defaults to every shop connected to Google Ads}
            {--days=90 : How many days of clicks to read back from Google, at most 90}
            {--dry-run : Show what would be merged without writing anything}';
    }

    public function getCommandDescription(): string
    {
        return 'Merge campaigns minted from a click URL id Google does not know into the campaign their clicks belong to';
    }

    public function asCommand(Command $command): int
    {
        /** @var Collection<int, Shop> $shops */
        $shops = FetchGoogleAdsCampaigns::connectedShops($command->argument('shop'));

        if ($shops->isEmpty()) {
            $command->error('No shop connected to Google Ads matches: a customer id and a refresh token are both needed.');

            return Command::FAILURE;
        }

        $rows   = [];
        $failed = 0;

        foreach ($shops as $shop) {
            try {
                array_push($rows, ...$this->handle($shop, (int) $command->option('days'), (bool) $command->option('dry-run')));
            } catch (Throwable $e) {
                $failed++;
                report($e);
                $command->error("$shop->slug: ".$e->getMessage());
            }
        }

        $command->table(['shop', 'url campaign id', 'resolved clicks', 'Google campaign', 'share', 'customers', 'orders', 'result'], $rows);

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
