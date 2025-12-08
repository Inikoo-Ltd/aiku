<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 14 Feb 2023 11:14:57 Malaysia Time, Ubud, Bali
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Accounting\Invoice\DeleteInvoice;
use App\Models\Accounting\Invoice;
use App\Transfers\SourceOrganisationService;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteInvoiceFromAurora
{
    use AsAction;

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?Invoice
    {
        if ($invoice = Invoice::withTrashed()->where('source_id', $organisationSourceId)->first()) {
            if (! $invoice->trashed()) {
                $deletedInvoiceData = $organisationSource->fetchDeletedInvoice($organisationSourceId);

                DeleteInvoice::run($invoice, [
                    'data' => [
                        'deleted' => ['note' => $deletedInvoiceData['note']],
                    ],
                ]);
            }
        } else {
            return FetchAuroraDeletedInvoices::run($organisationSource, $organisationSourceId);
        }

        return null;
    }
}
