<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Feb 2023 16:47:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

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
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;


class PrintShipmentLabel extends OrgAction
{
    use WithPrintNode;

    public function handle(Shipment $shipment, array $modelData)
    {

        switch($shipment->label_type) {
            case ShipmentLabelTypeEnum::PDF:
                return $this->printPdf(
                    title: $shipment->tracking,
                    printId: $modelData['printer_id'],
                    pdfBaset64: $shipment->label
                );
        }

    }

    public function rules(): array
    {
        return [
            'printer_id'      => ['required', 'integer'],
        ];
    }

    public function afterValidator(Validator $validator): void{
        $printerId = $this->get('printer_id');
        if ($printerId) {
            if(!$this->isExistPrinter($printerId)){
                $validator->errors()->add('printer_id', 'Printer does not exist');
            }
        }
    }

    public function asController(Shipment $shipment, array $modelData)
    {
        $this->initialisationFromGroup($shipment->group, $modelData);

        return $this->handle($shipment, $this->validatedData);
    }


}
