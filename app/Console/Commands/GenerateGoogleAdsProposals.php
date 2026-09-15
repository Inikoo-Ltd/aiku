<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Console\Commands;

use App\Actions\CRM\TrafficSource\AdProposals\GenerateGoogleAdsProposals as GenerateGoogleAdsProposalsAction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateGoogleAdsProposals extends Command
{
    protected $signature = 'google-ads:propose
                           {shop? : Shop slug, defaults to every shop connected to Google Ads}
                           {--dry-run : Work out the suggestions and report them without filing any}';

    protected $description = 'Work out what is worth changing in each shop\'s Google Ads account';

    public function handle(): int
    {
        if (blank(config('services.google_ads.developer_token'))) {
            $this->warn('No Google Ads developer token configured (services.google_ads.developer_token).');

            return Command::FAILURE;
        }

        $shops = GenerateGoogleAdsProposalsAction::connectedShops($this->argument('shop'));

        if ($shops->isEmpty()) {
            $this->error($this->argument('shop')
                ? "Shop '{$this->argument('shop')}' is unknown, or has no Google Ads customer id and refresh token."
                : 'No shop is configured for Google Ads: a customer id and a refresh token.');

            return Command::FAILURE;
        }

        $failed = 0;

        foreach ($shops as $shop) {
            try {
                $summary = GenerateGoogleAdsProposalsAction::run($shop, (bool) $this->option('dry-run'));

                $this->info(sprintf(
                    '%s: %d suggestion(s) from %d candidate(s), %d set aside%s',
                    $shop->slug,
                    $summary['proposed'],
                    $summary['candidates'],
                    $summary['skipped'],
                    $this->option('dry-run') ? ' [dry run, nothing filed]' : '',
                ));
            } catch (Throwable $e) {
                $failed++;
                $this->error("{$shop->slug}: ".$e->getMessage());
                Log::error('Google Ads proposal generation failed', ['shop' => $shop->slug, 'error' => $e->getMessage()]);
            }
        }

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
