<?php

/*
 * author Arya Permana - Kirin
 * created on 19-12-2024-09h-49m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Catalogue\Asset\Hydrators;

use App\Actions\Traits\Hydrators\WithHydrateIntervals;
use App\Actions\Traits\WithEnumStats;
use App\Models\Catalogue\Asset;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class AssetHydrateInvoicedCustomers implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;
    use WithHydrateIntervals;

    public string $jobQueue = 'low-priority';

    public function getJobUniqueId(Asset $asset): string
    {
        return $asset->id;
    }


    public function handle(Asset $asset): void
    {
        $dateRanges = $this->getDateRanges();
        $stats      = [];

        foreach ($dateRanges as $key => $range) {
            if ($key === 'all') {
                $invoices = $asset->invoiceTransactions()
                    ->with('invoice')
                    ->get()
                    ->pluck('invoice')
                    ->filter()
                    ->unique('customer_id');
            } else {
                [$start, $end] = $range;

                $invoices = $asset->invoiceTransactions()
                    ->with('invoice')
                    ->whereHas('invoice', function ($query) use ($start, $end) {
                        $query->whereBetween('created_at', [$start, $end]);
                    })
                    ->get()
                    ->pluck('invoice')
                    ->filter()
                    ->unique('customer_id');
            }

            $stats["customers_invoiced_$key"] = $invoices->count();
        }

        $asset->orderingIntervals()->update($stats);
    }

}
