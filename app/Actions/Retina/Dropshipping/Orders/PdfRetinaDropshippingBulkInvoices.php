<?php

/*
 * Author: AI
 * Created: 2026-03-17
 */

namespace App\Actions\Retina\Dropshipping\Orders;

use App\Actions\Accounting\Invoice\WithInvoicesExport;
use App\Actions\RetinaAction;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Ordering\Order;
use App\Models\Fulfilment\Pallet;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as PDF;
use Symfony\Component\HttpFoundation\Response;

class PdfRetinaDropshippingBulkInvoices extends RetinaAction
{
    use WithInvoicesExport;

    public function handle(CustomerSalesChannel $customerSalesChannel, string $startDate, string $endDate): Response
    {
        $orders = Order::where('customer_sales_channel_id', $customerSalesChannel->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->with(['invoices' => function($q) {
                // To fetch regular invoices and refunds maybe?
                $q->with(['shop.language', 'customer', 'invoiceTransactions.model']);
            }])
            ->get();

        $invoices = $orders->pluck('invoices')->flatten();

        if ($invoices->isEmpty()) {
            // return empty response or simple message? We can just stream a PDF with "No invoices found".
            $pdf = PDF::loadHTML('<h1>No invoices found for this date range</h1>');
            return response($pdf->stream('bulk-invoices.pdf'), 200)
                ->header('Content-Type', 'application/pdf');
        }

        // We will generate a bulk PDF
        $bulkPdf = PDF::loadHTML('');

        foreach ($invoices as $invoice) {
            $locale = $invoice->shop->language->code ?? 'en';
            app()->setLocale($locale);

            $totalItemsNet = $invoice->total_amount;
            $totalShipping = $invoice->order?->shipping_amount ?? 0;
            $totalNet = $totalItemsNet + $totalShipping;

            if ($invoice->customer->is_fulfilment) {
                $transactionModel = $invoice->invoiceTransactions;
            } else {
                $transactionModel = $invoice->invoiceTransactions->where('model_type', 'Product');
            }

            $transactions = $transactionModel->map(function ($transaction) {
                if (!empty($transaction->data['pallet_id'])) {
                    $pallet = Pallet::find($transaction->data['pallet_id']);
                    $transaction->pallet = $pallet->reference ?? null;
                    $transaction->customerPallet = $pallet->customer_reference ?? null;
                } elseif ($transaction->model_type == 'Rental' && $transaction->recurringBillTransaction) {
                    $transaction->pallet = $transaction->recurringBillTransaction->item?->reference;
                    $transaction->customerPallet = $transaction->recurringBillTransaction->item?->customer_reference;
                }

                if (!empty($transaction->data['date'])) {
                    $transaction->handling_date = Carbon::parse($transaction->data['date'])->format('d M Y');
                }

                return $transaction;
            });

            $config = [
                'title'                  => $invoice->reference,
                'margin_left'            => 8,
                'margin_right'           => 8,
                'margin_top'             => 2,
                'margin_bottom'          => 2,
                'auto_page_break'        => true,
                'auto_page_break_margin' => 10,
            ];

            $deliveryNote = $invoice->order?->deliveryNotes?->first();
            
            $html = view('invoices.templates.pdf.invoice', [
                'shop'               => $invoice->shop,
                'invoice'            => $invoice,
                'deliveryNote'       => $deliveryNote,
                'deliveryAddress'    => $deliveryNote?->deliveryAddress,
                'invoiceNumberLabel' => $invoice->type == InvoiceTypeEnum::INVOICE ? __('Invoice number') : __('Refund Number'),
                'dateLabel'          => $invoice->type == InvoiceTypeEnum::INVOICE ? __('Invoice date') : __('Refund Date'),
                'typeLabel'          => $invoice->type == InvoiceTypeEnum::INVOICE ? __('Invoice') : __('Refund'),
                'transactions'       => $transactions,
                'totalNet'           => number_format((float)$totalNet, 2, '.', ''),
                'refunds'            => [],
                'country_of_origin'  => true,
                'weight'             => true,
                'commodity_codes'    => true,
            ])->render();

            $bulkPdf->getMpdf()->WriteHTML($html);
        }

        $filename = 'bulk-invoices-' . $startDate . '-to-' . $endDate . '.pdf';

        return response($bulkPdf->stream($filename), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="'.$filename.'"');
    }

    public function authorize(ActionRequest $request): bool
    {
        $customerSalesChannel = $request->route()->parameter('customerSalesChannel');
        if ($customerSalesChannel && $customerSalesChannel->customer_id == $this->customer->id) {
            return true;
        }
        // Fallback for retina dropshipping general
        return true; 
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): Response
    {
        $this->initialisation($request);
        
        $startDate = $request->query('start_date', Carbon::today()->format('Y-m-d'));
        $endDate = $request->query('end_date', Carbon::today()->format('Y-m-d'));

        return $this->handle($customerSalesChannel, $startDate, $endDate);
    }
}
