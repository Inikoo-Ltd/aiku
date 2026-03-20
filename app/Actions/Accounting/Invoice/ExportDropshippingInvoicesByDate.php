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

    public function handle(Customer $customer, ?string $startDate = null, ?string $endDate = null) : Response
    {
        if(!$startDate){
            return response()->json(['error' => 'Start date is required'], 400);
        }

        $query = Invoice::where('invoices.customer_id', $customer->id);

        if($startDate && $endDate){
            $query->whereBetween('date',
            [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay()
            ]);
            $fileName = 'invoices-' . $startDate . '_to_' . $endDate;

        } 
        else {
            $query->whereDate('date', Carbon::parse($startDate)->toDateString());
            $fileName = 'invoices-' . $startDate;    
        }

        $invoices = $query->get();

        if($invoices->isEmpty()) {
            return response()->json(['error' => 'No invoices found for the given date range'], 404);
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