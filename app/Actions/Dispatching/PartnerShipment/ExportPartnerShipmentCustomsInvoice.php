<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 04 Nov 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\PartnerShipment;

use App\Actions\OrgAction;
use App\Exports\Dispatching\PartnerShipmentCustomsInvoiceExport;
use App\Models\Dispatching\PartnerCustomsInvoice;
use App\Models\Dispatching\PartnerShipment;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportPartnerShipmentCustomsInvoice extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return PartnerShipment::canBeSeenBy($request->user(), $this->organisation);
    }

    public function handle(PartnerCustomsInvoice $customsInvoice): BinaryFileResponse
    {
        return Excel::download(new PartnerShipmentCustomsInvoiceExport($customsInvoice), $customsInvoice->reference.'.xlsx');
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, PartnerCustomsInvoice $partnerCustomsInvoice, ActionRequest $request): BinaryFileResponse
    {
        abort_unless($partnerCustomsInvoice->organisation_id === $organisation->id && $warehouse->organisation_id === $organisation->id && $partnerCustomsInvoice->partnerShipment->warehouse_id === $warehouse->id, 404);
        $this->initialisation($organisation, $request);

        return $this->handle($partnerCustomsInvoice);
    }
}
