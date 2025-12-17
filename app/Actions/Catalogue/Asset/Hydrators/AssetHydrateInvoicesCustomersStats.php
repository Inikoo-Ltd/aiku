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
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class AssetHydrateInvoicesCustomersStats implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;
    use WithHydrateDeliveryNotes;

    public string $jobQueue = 'sales';

    public function getJobUniqueId(int $assetID): string
    {
        return $assetID;
    }

    public function handle(int $assetID): void
    {
        $asset = Asset::find($assetID);
        if (!$asset) {
            return;
        }

        $distinctCustomers = DB::table('invoice_transactions')
            ->join('invoices', 'invoices.id', '=', 'invoice_transactions.invoice_id')
            ->where('invoice_transactions.asset_id', $asset->id)
            ->where('invoices.in_process', false)
            ->where('invoices.type', InvoiceTypeEnum::INVOICE)
            ->whereNotNull('invoices.customer_id')
            ->distinct()
            ->count('invoices.customer_id');

        $stats = [
            'number_invoiced_customers' => $distinctCustomers,
        ];


        $asset->orderingStats()->update($stats);
    }

}
