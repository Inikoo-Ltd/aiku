<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 13 Aug 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\CRM\Customer;

use App\Models\Catalogue\Shop;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RebuildCustomerRfmSnapshots
{
    use AsAction;

    public string $commandSignature = 'rebuild:customer-rfm-snapshots {--shop= : Shop slug, all shops when omitted} {--days=0 : Number of past days to rebuild on top of today}';

    public function handle(Shop $shop, Carbon $from, Carbon $to): int
    {
        $rebuilt = 0;

        for ($date = $from->copy()->startOfDay(); $date->lessThanOrEqualTo($to); $date->addDay()) {
            $summary = CalculateCustomerRfmSegmentation::run($shop->id, $date);

            if (empty($summary)) {
                continue;
            }

            StoreCustomerRfmSnapshot::run($shop->id, $summary, $date->copy()->endOfDay());
            $rebuilt++;
        }

        return $rebuilt;
    }

    public function asCommand(Command $command): int
    {
        $shopSlug = $command->option('shop');
        $days     = (int) $command->option('days');

        $shops = Shop::when($shopSlug, fn ($query) => $query->where('slug', $shopSlug))->get();

        if ($shops->isEmpty()) {
            $command->error('No shop found.');

            return 1;
        }

        $to   = now();
        $from = $to->copy()->subDays(max($days, 0));

        foreach ($shops as $shop) {
            $rebuilt = $this->handle($shop, $from, $to);
            $command->line("{$shop->slug}: {$rebuilt} snapshots rebuilt");
        }

        return 0;
    }
}
