<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 10-01-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Accounting\Invoice;

use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Models\Accounting\Invoice;
use App\Models\Fulfilment\Pallet;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Arr;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as PDF;
use Sentry;

trait WithInvoicesExport
{
    public function processDataExportPdf(Invoice $invoice, array $options = []): \Symfony\Component\HttpFoundation\Response
    {
        $locale = $invoice->shop->language->code;
        app()->setLocale($locale);

        try {
            $totalItemsNet = $invoice->total_amount;
            $totalShipping = $invoice->order?->shipping_amount ?? 0;

            $totalNet = $totalItemsNet + $totalShipping;

            $invoiceTransactions = $invoice->invoiceTransactions()->with('model')->get();

            if ($invoice->customer->is_fulfilment) {
                $transactionModel = $invoiceTransactions;
            } else {
                $transactionModel = $invoiceTransactions->where('model_type', 'Product');
            }

            $transactions = $transactionModel->map(function ($transaction) {
                if (!empty($transaction->data['pallet_id'])) {
                    $pallet                      = Pallet::find($transaction->data['pallet_id']);
                    $transaction->pallet         = $pallet->reference;
                    $transaction->customerPallet = $pallet->customer_reference;
                } elseif ($transaction->model_type == 'Rental' && $transaction->recurringBillTransaction) {
                    $transaction->pallet         = $transaction->recurringBillTransaction->item?->reference;
                    $transaction->customerPallet = $transaction->recurringBillTransaction->item?->customer_reference;
                }

                if (!empty($transaction->data['date'])) {
                    $transaction->handling_date = Carbon::parse($transaction->data['date'])->format('d M Y');
                }

                return $transaction;
            });

            $orderData = $invoice->order?->data ?? [];
            $recipientName = null;
            if (!empty($orderData['shipping_address']['name'])) {
                $recipientName = $orderData['shipping_address']['name'];
            } elseif (!empty($orderData['shopify_data']['shipping_address']['firstName']) || !empty($orderData['shopify_data']['shipping_address']['lastName'])) {
                $recipientName = trim(($orderData['shopify_data']['shipping_address']['firstName'] ?? '') . ' ' . ($orderData['shopify_data']['shipping_address']['lastName'] ?? ''));
            } elseif (!empty($orderData['delivery_data']['firstName']) || !empty($orderData['delivery_data']['lastName'])) {
                $recipientName = trim(($orderData['delivery_data']['firstName'] ?? '') . ' ' . ($orderData['delivery_data']['lastName'] ?? ''));
            } elseif (!empty($orderData['delivery_data']['name'])) {
                $recipientName = $orderData['delivery_data']['name'];
            } elseif (!empty($orderData['delivery_data']['contact_name'])) {
                $recipientName = $orderData['delivery_data']['contact_name'];
            } elseif ($invoice->order?->customerClient) {
                $recipientName = $invoice->order->customerClient->contact_name ?? $invoice->order->customerClient->name;
            } elseif ($invoice->customerClient) {
                $recipientName = $invoice->customerClient->contact_name ?? $invoice->customerClient->name;
            }

            //$refund = $invoice->type == InvoiceTypeEnum::REFUND;
            $refundData = [];
            // todo remove this i dont think we need this
            //            if ($refund) {
            //                foreach ($invoice->invoiceTransactions->where('model_type', 'Product') as $invoiceTransaction) {
            //
            //                    $refunded = false;
            //                    if ($invoiceTransaction->transaction) {
            //                        $refunded = $invoiceTransaction->quantity < $invoiceTransaction->transaction->quantity_ordered;
            //                    }
            //
            //                    if ($refunded) {
            //                        $quantityRefunded = $invoiceTransaction->transaction->quantity_ordered - $invoiceTransaction->quantity;
            //
            //                        $totalRefunded = $invoiceTransaction->historicAsset->price * $quantityRefunded;
            //
            //                        if ($invoiceTransaction->is_tax_only) {
            //                            $totalRefunded = $invoiceTransaction->tax_amount;
            //                        }
            //
            //                        $refundData[] = [
            //                            'code' => $invoiceTransaction->historicAsset->code,
            //                            'description' => $invoiceTransaction->historicAsset->name,
            //                            'price' =>  $invoiceTransaction->historicAsset->price,
            //                            'quantity' => $quantityRefunded,
            //                            'is_tax_only' => $invoiceTransaction->is_tax_only,
            //                            'total' => $totalRefunded
            //                        ];
            //                    }
            //                }
            //            }

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
            $filename     = $invoice->slug.'-'.now()->format('Y-m-d');
            $pdf          = PDF::loadView('invoices.templates.pdf.invoice', [
                'shop'               => $invoice->shop,
                'invoice'            => $invoice,
                'deliveryNote'       => $deliveryNote,
                'deliveryAddress'    => $deliveryNote?->deliveryAddress,
                'recipientName'      => $recipientName,
                'invoiceNumberLabel' => $invoice->type == InvoiceTypeEnum::INVOICE ? __('Invoice number') : __('Refund Number'),
                'dateLabel'          => $invoice->type == InvoiceTypeEnum::INVOICE ? __('Invoice date') : __('Refund Date'),
                'typeLabel'          => $invoice->type == InvoiceTypeEnum::INVOICE ? __('Invoice') : __('Refund'),
                'transactions'       => $transactions,
                'totalNet'           => number_format($totalNet, 2, '.', ''),
                'refunds'            => $refundData,
                'pro_mode'             => Arr::get($options, 'pro_mode', false),
                'country_of_origin'   => Arr::get($options, 'country_of_origin', false),
                'rrp'                  => Arr::get($options, 'rrp', false),
                'parts'                => Arr::get($options, 'parts', false),
                'commodity_codes'      => Arr::get($options, 'commodity_codes', false),
                'weight'               => Arr::get($options, 'weight', false),
                'barcode'              => Arr::get($options, 'barcode', false),
                'cpnp'                 => Arr::get($options, 'cpnp', false),
                'hide_payment_status'  => Arr::get($options, 'hide_payment_status', false),
                'group_by_tariff_code' => Arr::get($options, 'group_by_tariff_code', false),
                'show_dispatch_totals' => Arr::get($options, 'show_dispatch_totals', false),
                'dispatch_total_skos'  => $deliveryNote?->total_skos > 0
                    ? $deliveryNote->total_skos
                    : $transactions->sum(fn ($t) => $t->quantity ?? 0),
                'dispatch_total_units' => $deliveryNote?->total_units > 0
                    ? $deliveryNote->total_units
                    : $transactions->sum(fn ($t) => ($t->quantity ?? 0) * ($t->model?->units ?? 1)),
            ], [], $config);

            $isAttachIsdocToPdf = Arr::get($invoice->organisation->settings, "invoice_export.attach_isdoc_to_pdf", false);

            if ($isAttachIsdocToPdf && !app()->environment('local')) {
                try {
                    $outputFile = AttacheIsDocToInvoicePdf::make()->handle($invoice, $pdf, $filename);

                    return response()->file($outputFile, [
                        'Content-Type'        => 'application/pdf',
                        'Content-Disposition' => 'inline; filename="'.$filename.'.pdf"',
                    ]);
                } catch (Exception $e) {
                    return response()->json(['error' => 'Failed to generate ISDOC'.' '.$e->getMessage()], 404);
                }
            }

            return response($pdf->stream($filename.'.pdf'), 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="'.$filename.'.pdf"');
        } catch (Exception $e) {
            Sentry::captureException($e);

            return response()->json(['error' => 'Failed to generate PDF'], 404);
        }
    }
}
