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
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Models\Accounting\Invoice;
use App\Models\Catalogue\Asset;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class AssetHydrateInvoicesStats implements ShouldBeUnique
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
                ->unique('id');

        $stats          = [
            'number_invoices'              => $invoices->count(),
            'number_invoices_type_invoice' => $invoices->where('type', InvoiceTypeEnum::INVOICE)->count(),
            'last_invoiced_at'             => $invoices->max('date'),
        ];

        $stats['number_invoices_type_refund'] = $stats['number_invoices'] - $stats['number_invoices_type_invoice'];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'invoices',
                field: 'type',
                enum: InvoiceTypeEnum::class,
                models: Invoice::class,
                where: function ($q) use ($invoices) {
                    $q->whereIn('id', $invoices->pluck('id'));
                }
            )
        );


        $asset->orderingStats()->update($stats);
    }
}
