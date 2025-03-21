<?php
/*
 * author Arya Permana - Kirin
 * created on 21-03-2025-09h-20m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Accounting\InvoiceTransaction;

use App\Actions\Traits\WithExportData;
use App\Exports\Accounting\FulfilmentInvoiceTransactionsExport;
use App\Exports\Accounting\InvoiceTransactionsExport;
use App\Exports\Inventory\LocationsExport;
use App\Models\Accounting\Invoice;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportFulfilmentInvoiceTransactions
{
    use AsAction;
    use WithAttributes;
    use WithExportData;

    /**
     * @throws \Throwable
     */
    public function handle(Invoice $invoice, array $modelData): BinaryFileResponse
    {
        $type = $modelData['type'];

        return $this->export(new FulfilmentInvoiceTransactionsExport($invoice), 'invoice_transactions', $type);
    }

    /**
     * @throws \Throwable
     */
    public function asController(Invoice $invoice, ActionRequest $request): BinaryFileResponse
    {
        $this->setRawAttributes($request->all());
        $this->validateAttributes();

        return $this->handle($invoice, $request->all());
    }
}
