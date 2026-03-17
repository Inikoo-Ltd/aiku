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

    public function handle(Customer $customer, string $date) : Response
    {
        try {
            $date = Carbon::parse($date)->toDateString();
        } catch (\Exception $e) {
            return response('Invalid date format. Please use YYYY-MM-DD.', 400);
        }

        $invoices = Invoice::where('customer_id', $customer->id)
            ->whereDate('date', $date)
            ->get();
        
        if($invoices->isEmpty()) {
            return response('No invoices found for the given date.', 404);
        }

        $mpdf = new Mpdf([
            'margin_left'   => 8,
            'margin_right'  => 8,
            'margin_top'    => 2,
            'margin_bottom' => 2,
        ]);

        foreach ($invoices as $index => $invoice) {
            $html = view('invoices.templates.pdf.invoice', [
                'shop'               => $invoice->shop,
                'invoice'            => $invoice,
                'deliveryNote'       => $invoice->order?->deliveryNotes?->first(),
                'deliveryAddress'    => $invoice->order?->deliveryNotes?->first()?->deliveryAddress,
                'invoiceNumberLabel' => $invoice->type == InvoiceTypeEnum::INVOICE ? __('Invoice number') : __('Refund Number'),
                'dateLabel'          => $invoice->type == InvoiceTypeEnum::INVOICE ? __('Invoice date') : __('Refund Date'),
                'typeLabel'          => $invoice->type == InvoiceTypeEnum::INVOICE ? __('Invoice') : __('Refund'),
                'transactions'       => $invoice->invoiceTransactions()->with('model')->get(),
                'totalNet'           => number_format($invoice->total_amount + ($invoice->order?->shipping_amount ?? 0), 2, '.', ''),
                'refunds'            => [],
                'country_of_origin'  => true,
                'weight'             => true,
                'commodity_codes'    => true,
            ])->render();

            if ($index > 0) {
                $mpdf->AddPage();
            }

            $mpdf->WriteHTML($html);
        }

        return response($mpdf->output('invoices-' . $date . '.pdf', 'S'), 200)
            ->header('Content-Type', 'application/pdf');
    }

    public function asController(ActionRequest $request): Response
    {
        $this->initialisation($request);
        
        return $this->handle(
            $this->customer,
            $request->query('date')
        );
    }

}