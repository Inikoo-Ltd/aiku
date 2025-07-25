<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 10-01-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Accounting\Invoice;

use App\Models\Accounting\Invoice;
use App\Models\Fulfilment\Pallet;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Arr;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as PDF;
use Sentry;

trait WithInvoicesExport
{
    public function processDataExportPdf(Invoice $invoice): \Symfony\Component\HttpFoundation\Response
    {
        try {
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
                    $transaction->pallet = $pallet->reference;
                    $transaction->customerPallet = $pallet->customer_reference;
                } elseif ($transaction->model_type == 'Rental' && $transaction->recurringBillTransaction) {
                    $transaction->pallet = $transaction->recurringBillTransaction->item->reference;
                    $transaction->customerPallet = $transaction->recurringBillTransaction->item->customer_reference;
                }

                if (!empty($transaction->data['date'])) {
                    $transaction->handling_date = Carbon::parse($transaction->data['date'])->format('d M Y');
                }

                return $transaction;
            });
            
            $refund = $invoice->payment_amount > $invoice->total_amount;
            $refundData= [];
            if($refund) {
                foreach ($invoice->invoiceTransactions->where('model_type', 'Product') as $invoiceTransaction) {
                    $refunded = $invoiceTransaction->quantity < $invoiceTransaction->transaction->quantity_ordered;
                    if($refunded) {
                        $quantityRefunded = $invoiceTransaction->transaction->quantity_ordered - $invoiceTransaction->quantity;
                        $totalRefunded = $invoiceTransaction->historicAsset->price * $quantityRefunded;
                        $refundData[] = [
                            'code' => $invoiceTransaction->historicAsset->code,
                            'description' => $invoiceTransaction->historicAsset->name,
                            'price' =>  $invoiceTransaction->historicAsset->price,
                            'quantity' => $quantityRefunded,
                            'total' => $totalRefunded
                        ];
                    }
                }
            }

            // dd($refundData);

            $config = [
                'title'                  => $invoice->reference,
                'margin_left'            => 8,
                'margin_right'           => 8,
                'margin_top'             => 2,
                'margin_bottom'          => 2,
                'auto_page_break'        => true,
                'auto_page_break_margin' => 10,
            ];

            $filename = $invoice->slug . '-' . now()->format('Y-m-d');
            $pdf      = PDF::loadView('invoices.templates.pdf.invoice', [
                'shop'          => $invoice->shop,
                'invoice'       => $invoice,
                'context'       => $invoice->original_invoice_id ? 'Refund' : 'Invoice',
                'transactions'  => $transactions,
                'totalNet'      => number_format($totalNet, 2, '.', ''),
                'refunds'       => $refundData
            ], [], $config);

            $isAttachIsdocToPdf = Arr::get($invoice->organisation->settings, "invoice_export.attach_isdoc_to_pdf", false);

            if ($isAttachIsdocToPdf) {
                try {
                    $outputFile = AttacheIsDocToInvoicePdf::make()->handle($invoice, $pdf, $filename);
                    return response()->file($outputFile, [
                        'Content-Type' => 'application/pdf',
                        'Content-Disposition' => 'inline; filename="' . $filename . '.pdf"',
                    ]);
                } catch (Exception $e) {
                    return response()->json(['error' => 'Failed to generate ISDOC'.' '.$e->getMessage()], 404);
                }
            }

            return response($pdf->stream($filename . '.pdf'), 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="' . $filename . '.pdf"');
        } catch (Exception $e) {
            Sentry::captureException($e);
            return response()->json(['error' => 'Failed to generate PDF'], 404);
        }
    }
}
