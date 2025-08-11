<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 24 Apr 2023 20:23:18 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Billing;

use App\Actions\Accounting\Invoice\PdfInvoice;
use App\Actions\RetinaAction;
use App\Models\Accounting\Invoice;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\Response;

class RetinaPdfInvoice extends RetinaAction
{
    public Invoice $invoice;

    public function handle(Invoice $invoice): Response
    {
        return PdfInvoice::run($invoice);
    }

    public function authorize(ActionRequest $request): bool
    {
        return $this->customer->id == $request->route()->parameter('invoice')->customer_id;
    }

    public function asController(Invoice $invoice, ActionRequest $request): Response
    {
        $this->initialisation($request);

        return $this->handle($invoice);
    }
}
