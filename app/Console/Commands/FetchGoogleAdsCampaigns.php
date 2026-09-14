<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 07 Sep 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Console\Commands;

use App\Actions\CRM\TrafficSourceCampaign\GoogleAds\FetchGoogleAdsCampaigns as FetchGoogleAdsCampaignsAction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class FetchGoogleAdsCampaigns extends Command
{
    protected $signature = 'google-ads:fetch-campaigns
                           {shop? : Shop slug, defaults to every shop connected to Google Ads}
                           {--days=30 : How many days of daily figures to read, counting back from today}
                           {--dry-run : Fetch and report without writing anything}';

    protected $description = 'Fetch campaigns, their daily performance and their spend from the Google Ads API';

    public function handle(): int
    {
        if (blank(config('services.google_ads.developer_token'))) {
            $this->warn('No Google Ads developer token configured (services.google_ads.developer_token).');

            return Command::FAILURE;
        }

        $shops = FetchGoogleAdsCampaignsAction::connectedShops($this->argument('shop'));

        if ($shops->isEmpty()) {
            $this->error($this->argument('shop')
                ? "Shop '{$this->argument('shop')}' is unknown, or has no Google Ads customer id and refresh token."
                : 'No shop is configured for Google Ads: a customer id and a refresh token.');

            return Command::FAILURE;
        }

        $days   = max((int) $this->option('days'), 1);
        $failed = 0;

        foreach ($shops as $shop) {
            try {
                $summary = FetchGoogleAdsCampaignsAction::run($shop, $days, (bool) $this->option('dry-run'));

                $this->info(sprintf(
                    '%s: %d campaign(s), %d day(s) of figures%s%s',
                    $shop->slug,
                    $summary['campaigns'],
                    $summary['metric_days'],
                    $summary['skipped'] > 0 ? ", {$summary['skipped']} skipped (reference claimed by another shop)" : '',
                    $summary['dry_run'] ? ' [dry run, nothing written]' : '',
                ));
            } catch (Throwable $e) {
                $failed++;
                $this->error("{$shop->slug}: ".$e->getMessage());
                Log::error('Google Ads campaign fetch failed', ['shop' => $shop->slug, 'error' => $e->getMessage()]);
            }
        }

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
