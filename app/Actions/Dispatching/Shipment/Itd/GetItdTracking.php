<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Feb 2023 16:47:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Shipment\Itd;

use App\Actions\OrgAction;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\Shipment;
use App\Models\Dispatching\Shipper;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class GetItdTracking extends OrgAction
{
    use AsAction;
    use WithAttributes;

    /**
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function handle(Shipper $shipper, array $modelData): Shipment
    {
        $result = $shipper->getTracking($modelData['tracking_number']);

        return $result;
    }

    public function rules(): array
    {
        return [
            'tracking_number' => ['required', 'max:64', 'string']
        ];
    }

    public function action(DeliveryNote $deliveryNote, Shipper $shipper, array $modelData): Shipment
    {
        $this->initialisation($deliveryNote->organisation, $modelData);

        return $this->handle($shipper, $this->validatedData);
    }
}
