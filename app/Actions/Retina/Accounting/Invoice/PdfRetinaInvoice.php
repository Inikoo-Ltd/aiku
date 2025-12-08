<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Fri, 17 Jan 2025 08:44:57 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Accounting\Invoice;

use App\Actions\Accounting\Invoice\WithInvoicesExport;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithExportData;
use App\Models\Accounting\Invoice;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\Response;

class PdfRetinaInvoice extends RetinaAction
{
    use WithExportData;
    use WithInvoicesExport;

    public function handle(Invoice $invoice): Response
    {
        return $this->processDataExportPdf($invoice);
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
        return [];
    }

    public function asController(Invoice $invoice, ActionRequest $request): Response
    {
        $this->initialisation($request);

        return $this->handle($invoice);
    }
}
