<?php

namespace App\Actions\Dispatching\Printer;

use App\Actions\Dispatching\Shipment\ApiCalls\CallApiApcGbShipping;
use App\Actions\Dispatching\Shipment\ApiCalls\CallApiDpdGbShipping;
use App\Actions\Dispatching\Shipment\ApiCalls\CallApiGlsSKShipping;
use App\Actions\Dispatching\Shipment\ApiCalls\CallApiItdShipping;
use App\Actions\Dispatching\Shipment\ApiCalls\DpdGbCallShipperApi;
use App\Actions\Dispatching\Shipment\ApiCalls\PostmenCallShipperApi;
use App\Actions\Dispatching\Shipment\ApiCalls\WhistlGbCallShipperApi;
use App\Actions\Dispatching\Shipment\Hydrators\ShipmentHydrateUniversalSearch;
use App\Actions\OrgAction;
use App\Enums\Dispatching\Shipment\ShipmentLabelTypeEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\Shipment;
use App\Models\Dispatching\Shipper;
use App\Models\Fulfilment\PalletReturn;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class PrintShipmentLabel extends OrgAction
{
    use WithPrintNode;

    public function handle(Shipment $shipment, ActionRequest $request)
    {
        switch($shipment->label_type) {
            case ShipmentLabelTypeEnum::PDF:
                return $this->printPdf(
                    title: $shipment->tracking,
                    printId: $this->get('printerId'),
                    pdfBaset64: $shipment->label
                );
        }

    }

    public function afterValidator(Validator $validator): void{
        $user = request()->user();
        $printerId = Arr::get($user->settings, 'preferred_printer_id', null);
        if (!$printerId) {
            throw ValidationException::withMessages([
                'messages' => __('You must set a preferred printer in your user settings!'),
            ]);
        }
        $this->set('printerId', $printerId);
    }

    public function asController(Shipment $shipment, ActionRequest $request)
    {
        $this->initialisationFromGroup($shipment->group, $request);

        return $this->handle($shipment, $request);
    }


}
