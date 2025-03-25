<?php

/*
 * author Arya Permana - Kirin
 * created on 21-03-2025-09h-49m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Accounting\Invoice\Transaction;

use App\Actions\Accounting\InvoiceTransaction\ExportFulfilmentInvoiceTransactions;
use App\Actions\Traits\WithExportData;
use App\Models\Accounting\Invoice;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportRetinaFulfilmentInvoiceTransactions
{
    use AsAction;
    use WithAttributes;
    use WithExportData;

    /**
     * @throws \Throwable
     */
    public function handle(Invoice $invoice, array $modelData): BinaryFileResponse
    {
        return ExportFulfilmentInvoiceTransactions::run($invoice, $modelData);
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
