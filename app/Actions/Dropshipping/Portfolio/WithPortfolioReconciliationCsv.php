<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Portfolio;

use Illuminate\Support\Arr;

trait WithPortfolioReconciliationCsv
{
    /**
     * @param  resource  $handle
     * @param  array<int, array>  $rows
     */
    public function writeReconciliationCsv($handle, array $rows): void
    {
        if (blank($rows)) {
            fputcsv($handle, ['portfolio_id']);

            return;
        }

        fputcsv($handle, array_keys(Arr::first($rows)));

        foreach ($rows as $row) {
            fputcsv($handle, array_map(
                fn ($value) => is_bool($value) ? ($value ? 'yes' : 'no') : $value,
                $row
            ));
        }
    }

    public function reconciliationFilename(string $channelSlug): string
    {
        return 'portfolio_reconciliation_'.$channelSlug.'_'.now()->format('Ymd_His').'.csv';
    }
}
