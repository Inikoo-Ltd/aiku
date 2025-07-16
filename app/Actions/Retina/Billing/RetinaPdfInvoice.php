<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 24 Apr 2023 20:23:18 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Billing;

use App\Actions\Accounting\Invoice\PdfInvoice;
use App\Actions\OrgAction;
use App\Models\Accounting\Invoice;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\Response;

class RetinaPdfInvoice extends OrgAction
{
    public function handle(Invoice $invoice): Response
    {
        return PdfInvoice::run($invoice);
    }


    public function asController(Invoice $invoice, ActionRequest $request): Response
    {
        return $this->handle($invoice);
    }
}
