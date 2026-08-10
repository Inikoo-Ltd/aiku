<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 06 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Console\Commands;

use App\Actions\CRM\TrafficSource\StoreTrafficSourceCost;
use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\TrafficSource;
use App\Models\CRM\TrafficSourceCampaign;
use App\Models\Helpers\Currency;
use Illuminate\Console\Command;

/**
 * Imports advertising spend from a CSV, one row per source (or campaign) per day:
 *
 *   shop,source,campaign,date,amount,currency
 *   uk,google-ads,22872123321,2026-08-01,153.22,GBP
 *   uk,meta-ads,,2026-08-01,88.40,GBP
 *
 * `campaign` is optional and matches `traffic_source_campaigns.reference`, which is what the touch
 * history already stores, so a Google Ads campaign id pasted straight out of the platform lines up
 * with the attribution data without any mapping step. A blank campaign records the source's total for
 * the day, and both can coexist for the same day.
 *
 * ponytail: deliberately a command over a CSV rather than an upload-tracked import. The Upload
 * machinery buys per-row audit records that nothing reads yet, and every existing importer that uses
 * it is broken against StoreUpload's current signature. Wire it in when the feature grows a UI.
 */
class ImportTrafficSourceCosts extends Command
{
    protected $signature = 'traffic-source:import-costs
                           {file : Path to the CSV file}
                           {--dry-run : Parse and validate without writing anything}';

    protected $description = 'Import advertising spend per traffic source and day from a CSV';

    private const COLUMNS = ['shop', 'source', 'campaign', 'date', 'amount', 'currency'];

    public function handle(): int
    {
        $path = $this->argument('file');

        if (!is_readable($path)) {
            $this->error("Cannot read '{$path}'.");

            return Command::FAILURE;
        }

        $handle = fopen($path, 'r');
        $header = fgetcsv($handle);

        if ($header === false) {
            $this->error('The file is empty.');
            fclose($handle);

            return Command::FAILURE;
        }

        $header  = array_map(fn ($column) => strtolower(trim((string) $column)), $header);
        $missing = array_diff(self::COLUMNS, $header);

        if ($missing) {
            $this->error('Missing column(s): '.implode(', ', $missing).'.');
            $this->line('Expected header: '.implode(',', self::COLUMNS));
            fclose($handle);

            return Command::FAILURE;
        }

        $imported   = 0;
        $failed     = 0;
        $rowNumber  = 1;

        while (($values = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if ($values === [null] || $values === []) {
                continue;
            }

            $row = array_combine($header, array_pad(array_slice($values, 0, count($header)), count($header), null));

            try {
                $this->importRow($row);
                $imported++;
            } catch (\Throwable $e) {
                $failed++;
                $this->line("  <fg=red>row {$rowNumber}</>: ".$e->getMessage());
            }
        }

        fclose($handle);

        $this->newLine();

        if ($this->option('dry-run')) {
            $this->info("Dry run: {$imported} row(s) would be imported, {$failed} rejected.");

            return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
        }

        $this->info("Imported {$imported} row(s), rejected {$failed}.");

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function importRow(array $row): void
    {
        $shop = Shop::where('slug', trim((string) $row['shop']))->first();

        if (!$shop) {
            throw new \RuntimeException("unknown shop '{$row['shop']}'");
        }

        $type = TrafficSourcesTypeEnum::tryFrom(trim((string) $row['source']));

        if ($type === null) {
            throw new \RuntimeException("unknown traffic source '{$row['source']}'");
        }

        $trafficSource = TrafficSource::where('shop_id', $shop->id)
            ->where('type', $type->value)
            ->first();

        if (!$trafficSource) {
            throw new \RuntimeException("shop '{$shop->slug}' has no '{$type->value}' traffic source");
        }

        $currency = Currency::where('code', strtoupper(trim((string) $row['currency'])))->first();

        if (!$currency) {
            throw new \RuntimeException("unknown currency '{$row['currency']}'");
        }

        if (!is_numeric($row['amount'])) {
            throw new \RuntimeException("amount '{$row['amount']}' is not a number");
        }

        $campaignId = null;

        if (filled($row['campaign'])) {
            $campaignId = TrafficSourceCampaign::where('traffic_source_id', $trafficSource->id)
                ->where('reference', trim((string) $row['campaign']))
                ->value('id');

            if (!$campaignId) {
                throw new \RuntimeException("no campaign '{$row['campaign']}' for {$type->value} in shop '{$shop->slug}'");
            }
        }

        if ($this->option('dry-run')) {
            return;
        }

        StoreTrafficSourceCost::run($trafficSource, [
            'date'                       => trim((string) $row['date']),
            'source_amount'              => (float) $row['amount'],
            'source_currency_id'         => $currency->id,
            'traffic_source_campaign_id' => $campaignId,
        ]);
    }
}
