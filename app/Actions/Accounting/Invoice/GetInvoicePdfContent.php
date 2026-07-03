<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Invoice;

use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Models\Accounting\Invoice;
use App\Models\Fulfilment\Pallet;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as PDF;

class GetInvoicePdfContent
{
    use AsAction;

    /**
     * @throws \Mpdf\MpdfException
     */
    public function handle(Invoice $invoice, array $options = []): string
    {
        $locale = $invoice->shop->language->code;
        app()->setLocale($locale);

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

            if ($transaction->transaction_id) {
                $transaction->batch_codes = DB::table('delivery_note_items')
                    ->join('pickings', 'pickings.delivery_note_item_id', '=', 'delivery_note_items.id')
                    ->join('batch_codes', 'batch_codes.id', '=', 'pickings.batch_code_id')
                    ->where('delivery_note_items.transaction_id', $transaction->transaction_id)
                    ->whereNotNull('pickings.batch_code_id')
                    ->distinct()
                    ->pluck('batch_codes.code')
                    ->implode(', ');
            } else {
                $transaction->batch_codes = null;
            }

            return $transaction;
        });

        $orderData      = $invoice->order?->data ?? [];
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

        $pdf = PDF::loadView('invoices.templates.pdf.invoice', [
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
            'refunds'            => [],
            'pro_mode'             => Arr::get($options, 'pro_mode', false),
            'country_of_origin'    => Arr::get($options, 'country_of_origin', false),
            'rrp'                  => Arr::get($options, 'rrp', false),
            'parts'                => Arr::get($options, 'parts', false),
            'commodity_codes'      => Arr::get($options, 'commodity_codes', false),
            'weight'               => Arr::get($options, 'weight', false),
            'barcode'              => Arr::get($options, 'barcode', false),
            'cpnp'                 => Arr::get($options, 'cpnp', false),
            'hide_payment_status'  => Arr::get($options, 'hide_payment_status', false),
            'group_by_tariff_code' => Arr::get($options, 'group_by_tariff_code', false),
            'show_dispatch_totals' => Arr::get($options, 'show_dispatch_totals', false),
            'show_batch_code'      => Arr::get($options, 'show_batch_code', false),
            'dispatch_total_skos'     => $deliveryNote?->total_skos > 0 ? $deliveryNote->total_skos : null,
            'dispatch_total_units'    => $deliveryNote?->total_units > 0 ? $deliveryNote->total_units : null,
            'dispatch_total_quantity' => $transactions->sum(fn ($t) => $t->quantity ?? 0),
        ], [], $config);

        return $pdf->output();
    }
}
