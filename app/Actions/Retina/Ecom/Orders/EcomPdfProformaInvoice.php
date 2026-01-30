<?php

/*
 * Author: Vika Aqordi
 * Created on 06-10-2025-11h-53m
 * Github: https://github.com/aqordeon
 * Copyright: 2025
*/

namespace App\Actions\Retina\Ecom\Orders;

use App\Actions\Accounting\Invoice\WithInvoicesExport;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithExportData;
use App\Actions\Traits\WithProformaInvoicePdf;
use App\Models\Ordering\Order;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Symfony\Component\HttpFoundation\Response;

class EcomPdfProformaInvoice extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithInvoicesExport;
    use WithExportData;
    use WithProformaInvoicePdf {
        WithProformaInvoicePdf::rules insteadof WithExportData;
    }

    public function asController(Order $order, ActionRequest $request): Response
    {
        $this->initialisation($request);

        return $this->handle($order, $this->validatedData);
    }
}
