<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 03 Oct 2025 11:36:21 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\Accounting\Invoice\WithInvoicesExport;
use App\Actions\OrgAction;
use App\Actions\Traits\WithExportData;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Organisation;
use Carbon\Carbon;
use Exception;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as PDF;
use Sentry;
use Symfony\Component\HttpFoundation\Response;

class PdfProformaInvoice extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithExportData;
    use WithInvoicesExport;


    public function handle(Order $order, array $options): Response
    {
        try {
            $totalItemsNet = $order->total_amount;
            $totalShipping = $order->shipping_amount ?? 0;

            $totalNet = $totalItemsNet + $totalShipping;


            $transactionModel = $order->transactions->where('model_type', 'Product');


            $transactions = $transactionModel->map(function ($transaction) {
                if (!empty($transaction->data['date'])) {
                    $transaction->handling_date = Carbon::parse($transaction->data['date'])->format('d M Y');
                }

                return $transaction;
            });


            $config = [
                'title'                  => $order->reference,
                'margin_left'            => 8,
                'margin_right'           => 8,
                'margin_top'             => 2,
                'margin_bottom'          => 2,
                'auto_page_break'        => true,
                'auto_page_break_margin' => 10,
            ];


            $filename = $order->slug.'-'.now()->format('Y-m-d');
            $pdf      = PDF::loadView('invoices.templates.pdf.proforma-invoice', '', [
                'shop'         => $order->shop,
                'order'        => $order,
                'transactions' => $transactions,
                'totalNet'     => number_format($totalNet, 2, '.', ''),
            ], [], $config);


            return response($pdf->stream($filename.'.pdf'), 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="'.$filename.'.pdf"');
        } catch (Exception $e) {
            Sentry::captureException($e);

            return response()->json(['error' => 'Failed to generate PDF'], 404);
        }
    }

    public function rules(): array
    {
        return [
            'pro_mode'          => ['sometimes', 'boolean'],
            'country_of_origin' => ['sometimes', 'boolean'],
        ];
    }

    public function asController(Organisation $organisation, Shop $shop, Order $order, ActionRequest $request): Response
    {
        return $this->handle($order, $this->validatedData);
    }
}
