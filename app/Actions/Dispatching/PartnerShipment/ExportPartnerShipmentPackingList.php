<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 19 Nov 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\PartnerShipment;

use App\Actions\OrgAction;
use App\Exports\Dispatching\PartnerShipmentPackingListExport;
use App\Models\Dispatching\PartnerShipment;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportPartnerShipmentPackingList extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return PartnerShipment::canBeSeenBy($request->user(), $this->organisation);
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, PartnerShipment $partnerShipment, ActionRequest $request): BinaryFileResponse
    {
        abort_unless($partnerShipment->organisation_id === $organisation->id && $warehouse->organisation_id === $organisation->id && $partnerShipment->warehouse_id === $warehouse->id, 404);
        $this->initialisation($organisation, $request);

        return Excel::download(new PartnerShipmentPackingListExport($partnerShipment), $partnerShipment->reference.'.xlsx');
    }
}
