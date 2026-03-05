<?php

namespace App\Actions\Dispatching\Printer;

use App\Actions\OrgAction;
use App\Actions\Traits\WithPrintNode;
use App\Enums\Dispatching\Shipment\ShipmentLabelTypeEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\Shipment;
use App\Models\SysAdmin\User;
use Illuminate\Console\Command;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class PrintShipmentLabel extends OrgAction
{
    use WithPrintNode;

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(Shipment $shipment, User $user, Command|null $command=null): \Rawilk\Printing\Api\PrintNode\Resources\PrintJob|RedirectResponse
    {
        $printerId = Arr::get($user->settings, 'preferred_printer_id');
        $this->ensureClientInitialized();
        try {
            if ($shipment->combined_label_url) {
                $command?->info('Printing printPdfFromPdfUri');
                $res = $this->printPdfFromPdfUri(
                    title: $shipment->tracking,
                    printId: $printerId,
                    pdfUri: $shipment->combined_label_url
                );
            } elseif ($shipment->label && $shipment->label_type == ShipmentLabelTypeEnum::HTML) {
                $command?->info('Printing printRawBase64');
                $res = $this->printRawBase64(
                    title: $shipment->tracking,
                    printId: $printerId,
                    rawBase64: $shipment->label
                );
            } else {
                $command?->info('Printing printPdf');
                $res = $this->printPdf(
                    title: $shipment->tracking,
                    printId: $printerId,
                    pdfBase64: $shipment->label ?? ''
                );
            }

            return $res;
        } catch (\Throwable $e) {
            $command?->error('Error printing shipment label: '.$e->getMessage());
            throw ValidationException::withMessages([
                'messages' => __('Error printing shipment label').' '.$e->getMessage(),
            ]);
        }
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function afterValidator(Validator $validator): void
    {
        $user          = request()->user();
        $printerId     = Arr::get($user->settings, 'preferred_printer_id');
        $existsPrinter = $this->isExistPrinter($printerId);
        if (!$printerId || !$existsPrinter) {
            throw ValidationException::withMessages([
                'messages' => __('Preferred printer is not set or does not exist!'),
            ]);
        }

        $printByPrintNode = Arr::get($user->group->settings, 'printnode.print_by_printnode', false);
        if (!$printByPrintNode) {
            throw ValidationException::withMessages([
                'messages' => __('Print by Printnode is not enabled for your group!'),
            ]);
        }
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function asController(Shipment $shipment, ActionRequest $request): \Rawilk\Printing\Api\PrintNode\Resources\PrintJob|RedirectResponse
    {
        $this->initialisationFromGroup($shipment->group, $request);

        return $this->handle($shipment, $request->user());
    }

    public function getCommandSignature(): string
    {
        return 'printer:print-shipment-label {deliveryNote} {user}';
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function asCommand(Command $command): void
    {
        $user         = User::where('slug', $command->argument('user'))->firstOrFail();
        $deliveryNote = DeliveryNote::where('slug', $command->argument('deliveryNote'))->firstOrFail();
        foreach ($deliveryNote->shipments as $shipment) {
            $this->handle($shipment, $user, $command);
        }
    }

}
