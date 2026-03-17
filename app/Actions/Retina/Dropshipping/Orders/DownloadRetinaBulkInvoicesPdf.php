<?php

namespace App\Actions\Retina\Dropshipping\Orders;

use App\Actions\Accounting\Invoice\WithInvoicesExport;
use App\Actions\RetinaAction;
use App\Models\Accounting\Invoice;
use App\Models\Ordering\Order;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as PDF;

class DownloadRetinaBulkInvoicesPdf extends RetinaAction
{
    use WithInvoicesExport;

    public function handle(Request $request)
    {
        // ... build pdf ...
    }
}
