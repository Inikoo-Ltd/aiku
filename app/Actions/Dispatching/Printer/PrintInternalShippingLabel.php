<?php

namespace App\Actions\Dispatching\Printer;

use App\Actions\OrgAction;
use App\Models\Dispatching\DeliveryNote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as PDF;
use Rawilk\Printing\Api\PrintNode\Resources\PrintJob;
use App\Actions\Traits\WithPrintNode;
use Illuminate\Support\Facades\Log;

class PrintInternalShippingLabel extends OrgAction
{
    use WithPrintNode;

    public function handle(DeliveryNote $deliveryNote, array $modelData): PrintJob|RedirectResponse
    {
        try {
            $config = [
                'title'                  => $deliveryNote->reference,
                'margin_left'            => 8,
                'margin_right'           => 8,
                'margin_top'             => 2,
                'margin_bottom'          => 2,
                'auto_page_break'        => true,
                'auto_page_break_margin' => 10,
            ];

            $pdf = PDF::loadView('labels.templates.pdf.shipping', [
                'deliveryNoteRef'    => $deliveryNote->reference,
                'customer_name'      => $deliveryNote->customerClient->name,
                'customer_website'   => $deliveryNote->customerSalesChannel?->user?->name,
                'delivery_address'   => $deliveryNote->deliveryAddress->formatted_address,
                'customer_logo'      => $deliveryNote->customer->imageSources(64, 64),
                'deliveryNote'       => $deliveryNote
            ], [], $config);

            $pdfBase64 = base64_encode($pdf->output());
            return $this->printPdf(
                title: __('internal shipping for :internal shipping', ['internal shipping' => $this->get('internal shipping')]),
                printId: $this->get('printerId'),
                pdfBase64: $pdfBase64
            );
        } catch (\Throwable $e) {
            Log::error('Error printing internal shipping: ' . $e->getMessage());
            throw ValidationException::withMessages([
                'messages' => __('Error printing internal shipping'),
            ]);
        }
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function afterValidator(Validator $validator): void
    {
        $user      = request()->user();
        $printerId = Arr::get($user->settings, 'preferred_printer_id');
        if (!$printerId) {
            throw ValidationException::withMessages([
                'messages' => __('You must set a preferred printer in your user settings!'),
            ]);
        }

        $printByPrintNode = Arr::get($user->group->settings, 'printnode.print_by_printnode', false);
        if (!$printByPrintNode) {
            throw ValidationException::withMessages([
                'messages' => __('Print by Printnode is not enabled for your group!'),
            ]);
        }


        $this->set('printerId', $printerId);
    }

    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): RedirectResponse|PrintJob
    {
        return $this->handle($deliveryNote, $this->validatedData);
    }

    public function jsonResponse(PrintJob|RedirectResponse $result): PrintJob|RedirectResponse
    {
        return $result;
    }
}
