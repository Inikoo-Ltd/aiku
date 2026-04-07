<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 24 Apr 2023 20:23:18 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Invoice;

use App\Actions\Helpers\GoogleDrive\UploadFileGoogleDrive;
use App\Actions\Traits\WithExportData;
use App\Models\Accounting\Invoice;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as PDF;

class UploadPdfInvoice
{
    use AsAction;
    use WithAttributes;
    use WithExportData;

    /**
     * @throws \Mpdf\MpdfException
     */
    // todo: Upload Invoices to Google Drive #544
    public function handle(Invoice $invoice)
    {
        $totalItemsNet = (int) $invoice->total_amount;
        $totalShipping = (int) $invoice->order->shipping;

        $totalNet = $totalItemsNet + $totalShipping;

        $orderData = $invoice->order->data ?? [];
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

        $filename = $invoice->slug . '-' . now()->format('Y-m-d');

        $path = PDF::loadView('invoices.templates.pdf.invoice', [
            'shop'          => $invoice->shop,
            'invoice'       => $invoice,
            'recipientName' => $recipientName,
            'deliveryNote'  => $invoice->order?->deliveryNotes?->first(),
            'deliveryAddress' => $invoice->order?->deliveryNotes?->first()?->deliveryAddress,
            'invoiceNumberLabel' => $invoice->type == \App\Enums\Accounting\Invoice\InvoiceTypeEnum::INVOICE ? __('Invoice number') : __('Refund Number'),
            'dateLabel'          => $invoice->type == \App\Enums\Accounting\Invoice\InvoiceTypeEnum::INVOICE ? __('Invoice date') : __('Refund Date'),
            'typeLabel'          => $invoice->type == \App\Enums\Accounting\Invoice\InvoiceTypeEnum::INVOICE ? __('Invoice') : __('Refund'),
            'transactions'  => $invoice->invoiceTransactions,
            'totalNet'      => $totalNet
        ])->save($filename);

        return UploadFileGoogleDrive::run($invoice->organisation, $path);
    }
}
