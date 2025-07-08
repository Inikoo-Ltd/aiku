<?php

namespace App\Actions\Dispatching\Printer;

use App\Actions\OrgAction;
use App\Enums\Dispatching\Shipment\ShipmentLabelTypeEnum;
use App\Models\Dispatching\Shipment;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class PrintShipmentLabel extends OrgAction
{
    use WithPrintNode;

    public function handle(Shipment $shipment): \Rawilk\Printing\Api\PrintNode\Resources\PrintJob
    {
        if ($shipment->combined_label_url) {
            return $this->printPdfFromPdfUri(
                title: $shipment->tracking,
                printId: $this->get('printerId'),
                pdfUri: $shipment->combined_label_url
            );
        } elseif ($shipment->label && $shipment->label_type == ShipmentLabelTypeEnum::HTML) {
            return $this->printRawBase64(
                title: $shipment->tracking,
                printId: $this->get('printerId'),
                rawBase64: $shipment->label
            );
        }

        return $this->printPdf(
            title: $shipment->tracking,
            printId: $this->get('printerId'),
            pdfBase64: $shipment->label
        );
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

    public function asController(Shipment $shipment, ActionRequest $request): \Rawilk\Printing\Api\PrintNode\Resources\PrintJob
    {
        $this->initialisationFromGroup($shipment->group, $request);

        return $this->handle($shipment);
    }


}
