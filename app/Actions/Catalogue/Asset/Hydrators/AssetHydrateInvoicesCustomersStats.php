<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 06-01-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Catalogue\Asset\Hydrators;

use App\Actions\Traits\Hydrators\WithHydrateDeliveryNotes;
use App\Actions\Traits\WithEnumStats;
use App\Models\Catalogue\Asset;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class AssetHydrateInvoicesCustomersStats implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;
    use WithHydrateDeliveryNotes;

    public string $jobQueue = 'sales';

    public function getJobUniqueId(Asset $asset): string
    {
        return $asset->id;
    }

    public function handle(Asset $asset): void
    {
        $invoices = $asset->invoiceTransactions()
                    ->with('invoice')
                    ->get()
                    ->pluck('invoice')
                    ->filter()
                    ->unique('customer_id');

        $stats          = [
            'number_invoiced_customers'              => $invoices->count(),
        ];


        $asset->orderingStats()->update($stats);
    }

}
