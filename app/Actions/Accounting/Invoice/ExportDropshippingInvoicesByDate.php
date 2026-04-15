<?php

namespace App\Actions\Accounting\Invoice;

use Symfony\Component\HttpFoundation\Response;
use App\Models\Accounting\Invoice;
use App\Models\CRM\Customer;
use Mpdf\Mpdf;
use Carbon\Carbon;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Actions\RetinaAction;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;

class ExportDropshippingInvoicesByDate extends RetinaAction
{
    use AsAction;

    public function handle(Customer $customer, ?string $startDate = null, ?string $endDate = null, array $options = []): Response
    {
        if (!$startDate) {
            return response()->json(['error' => 'Start date is required'], 400);
        }

        $query = Invoice::where('invoices.customer_id', $customer->id);

        if ($startDate && $endDate) {
            $query->whereBetween(
                'date',
                [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay()
                ]
            );
            $fileName = 'invoices-' . $startDate . '_to_' . $endDate;

        } else {
            $query->whereDate('date', Carbon::parse($startDate)->toDateString());
            $fileName = 'invoices-' . $startDate;
        }

        $invoices = $query->get();

        if ($invoices->isEmpty()) {
            return response()->json(['error' => 'No invoices found for the given date range'], 404);
        }

        $mpdf = new Mpdf([
            'margin_left'   => 8,
            'margin_right'  => 8,
            'margin_top'    => 2,
            'margin_bottom' => 2,
        ]);

        foreach ($invoices as $index => $invoice) {
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

            $html = view('invoices.templates.pdf.dropshipping_invoice', [
                'shop'                  => $invoice->shop,
                'invoice'               => $invoice,
                'deliveryNote'          => $invoice->order?->deliveryNotes?->first(),
                'deliveryAddress'       => $invoice->order?->deliveryNotes?->first()?->deliveryAddress,
                'recipientName'         => $recipientName,
                'invoiceNumberLabel'    => $invoice->type == InvoiceTypeEnum::INVOICE ? __('Invoice number') : __('Refund Number'),
                'dateLabel'             => $invoice->type == InvoiceTypeEnum::INVOICE ? __('Invoice date') : __('Refund Date'),
                'typeLabel'             => $invoice->type == InvoiceTypeEnum::INVOICE ? __('Invoice') : __('Refund'),
                'transactions'          => $invoice->invoiceTransactions()->with('model')->get(),
                'totalNet'              => number_format($invoice->total_amount + ($invoice->order?->shipping_amount ?? 0), 2, '.', ''),
                'refunds'               => [],
                // Additional configs for the separated blade
                'pro_mode'              => false,
                'group_by_tariff_code'  => false,
                'hide_payment_status'   => false,
                'rrp'                   => false,
                'parts'                 => false,
                'barcode'               => false,
                'cpnp'                  => false,
                // Keep the original true values
                'country_of_origin'     => true,
                'weight'                => true,
                'commodity_codes'       => true,
            ])->render();

            if ($index > 0) {
                $mpdf->AddPage();
            }

            $mpdf->WriteHTML($html);
        }

        return response($mpdf->output('invoices-' . $fileName . '.pdf', 'S'), 200)
            ->header('Content-Type', 'application/pdf');
    }

    public function asController(ActionRequest $request): Response
    {
        $this->initialisation($request);

        return $this->handle(
            $this->customer,
            $request->query('startDate'),
            $request->query('endDate')
        );
    }

}
