<?php

/*
 * Created: Mon, 23 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026
 */

namespace App\Actions\CRM\TrafficSourceCampaign\GoogleAds;

use App\Actions\CRM\TrafficSource\ParseGoogleAdsClick;
use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use App\Models\Catalogue\Shop;
use App\Services\GoogleAds\GoogleAdsClient;
use App\Services\GoogleAds\GoogleAdsException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;
use Throwable;

/**
 * Stamps on each logged ad click the campaign Google itself says it belongs to.
 *
 * The gad_campaignid a Performance Max click carries in its URL is not the id of the campaign that
 * paid for it, and matches no campaign in the account, so clicks credited by the URL alone land on
 * a campaign with no spend while the one spending gets no orders. Google's click_view maps every
 * gclid to its real campaign, one day per query and only for the last 90 days.
 */
class FetchGoogleAdsClickCampaigns
{
    use AsAction;

    private const int CHUNK = 500;

    /**
     * @return array{days: int, google_clicks: int, stamped: int, dry_run: bool}
     * @throws GoogleAdsException
     */
    public function handle(Shop $shop, int $days = 3, bool $dryRun = false): array
    {
        $days            = $this->boundedDays($days);
        $campaignByClick = $this->read($this->clientFor($shop), $days);

        return [
            'days'          => $days,
            'google_clicks' => count($campaignByClick),
            'stamped'       => $dryRun ? 0 : $this->store($shop, $campaignByClick, $days),
            'dry_run'       => $dryRun,
        ];
    }

    public function clientFor(Shop $shop): GoogleAdsClient
    {
        return GoogleAdsClient::forShop($shop)
            ?? throw new RuntimeException(GoogleAdsClient::unreachableReason($shop) ?? __('Google Ads is not configured for this shop.'));
    }

    public function boundedDays(int $days): int
    {
        return min(max($days, 1), ParseGoogleAdsClick::WINDOW_DAYS);
    }

    /**
     * @return array<string, string> gclid => Google campaign id, from today back over $days days
     * @throws GoogleAdsException
     */
    public function read(GoogleAdsClient $client, int $days): array
    {
        $campaignByClick = [];

        for ($daysAgo = 0; $daysAgo < $this->boundedDays($days); $daysAgo++) {
            $date = now()->subDays($daysAgo)->toDateString();

            foreach ($client->search("SELECT click_view.gclid, campaign.id FROM click_view WHERE segments.date = '$date'") as $row) {
                $gclid      = (string) data_get($row, 'clickView.gclid');
                $campaignId = (string) data_get($row, 'campaign.id');

                if ($gclid !== '' && $campaignId !== '') {
                    $campaignByClick[$gclid] = $campaignId;
                }
            }
        }

        return $campaignByClick;
    }

    /**
     * @param array<string, string> $campaignByClick
     */
    public function store(Shop $shop, array $campaignByClick, int $days): int
    {
        $since   = now()->subDays($this->boundedDays($days) + 1);
        $stamped = 0;

        foreach (array_chunk($campaignByClick, self::CHUNK, true) as $chunk) {
            $values   = implode(', ', array_fill(0, count($chunk), '(?, ?)'));
            $bindings = [];

            foreach ($chunk as $gclid => $campaignId) {
                array_push($bindings, $gclid, $campaignId);
            }

            $stamped += DB::update(
                "UPDATE traffic_source_clicks AS clicks
                    SET google_campaign_ref = google.campaign_ref
                   FROM (VALUES $values) AS google(click_id, campaign_ref)
                  WHERE clicks.shop_id = ?
                    AND clicks.type = ?
                    AND clicks.created_at >= ?
                    AND clicks.click_id = google.click_id
                    AND clicks.google_campaign_ref IS DISTINCT FROM google.campaign_ref",
                [...$bindings, $shop->id, TrafficSourcesTypeEnum::GOOGLE_ADS->value, $since]
            );
        }

        return $stamped;
    }

    public function getCommandSignature(): string
    {
        return 'google-ads:fetch-click-campaigns
            {shop? : Shop slug, defaults to every shop connected to Google Ads}
            {--days=3 : How many days of clicks to read, counting back from today, at most 90}
            {--dry-run : Fetch and report without writing anything}';
    }

    public function getCommandDescription(): string
    {
        return 'Stamp each logged Google ad click with the campaign Google reports for it (click_view)';
    }

    public function asCommand(Command $command): int
    {
        $shops = FetchGoogleAdsCampaigns::connectedShops($command->argument('shop'));

        if ($shops->isEmpty()) {
            $command->error('No shop connected to Google Ads matches: a customer id and a refresh token are both needed.');

            return Command::FAILURE;
        }

        $failed = 0;

        foreach ($shops as $shop) {
            try {
                $summary = $this->handle($shop, (int) $command->option('days'), (bool) $command->option('dry-run'));

                $command->info(sprintf(
                    '%s: %d click(s) read from Google over %d day(s), %d stamped%s',
                    $shop->slug,
                    $summary['google_clicks'],
                    $summary['days'],
                    $summary['stamped'],
                    $summary['dry_run'] ? ' [dry run, nothing written]' : ''
                ));
            } catch (Throwable $e) {
                $failed++;
                report($e);
                $command->error("$shop->slug: ".$e->getMessage());
            }
        }

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
