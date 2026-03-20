<?php

/*
 * author Arya Permana - Kirin
 * created on 21-03-2025-09h-49m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Accounting\Invoice\Transaction;

use App\Actions\Accounting\InvoiceTransaction\ExportFulfilmentInvoiceTransactions;
use App\Actions\RetinaAction;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Accounting\Invoice;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportRetinaFulfilmentInvoiceTransactions extends RetinaAction
{
    /**
     * @throws \Throwable
     */
    public function handle(Invoice $invoice, array $modelData): BinaryFileResponse
    {
        if ($invoice->shop->type != ShopTypeEnum::FULFILMENT) {
            abort(422);
        }

        return ExportFulfilmentInvoiceTransactions::run($invoice, $modelData);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->customer->id == $request->route()->parameter('invoice')->customer_id) {
            return true;
        }

        return false;
    }


    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:pdf,xlsx,csv'],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function asController(Invoice $invoice, ActionRequest $request): BinaryFileResponse
    {
        $this->initialisation($request);

        return $this->handle($invoice, $this->validatedData);
    }
}
