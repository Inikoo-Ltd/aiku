<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Feb 2023 16:47:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Shipment\Itd;

use App\Actions\OrgAction;
use App\Models\Dispatching\Shipment;
use App\Models\Dispatching\Shipper;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class GetItdServices extends OrgAction
{
    use AsAction;
    use WithAttributes;

    /**
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function handle(Shipper $shipper): Shipment
    {
        return $shipper->getServices();
    }

    public function action(Shipper $shipper, array $modelData): Shipment
    {
        $this->initialisation($shipper->organisation, $modelData);

        return $this->handle($shipper);
    }
}
