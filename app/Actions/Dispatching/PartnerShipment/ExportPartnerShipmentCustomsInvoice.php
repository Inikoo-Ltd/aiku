<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 04 Nov 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\PartnerShipment;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Exports\Dispatching\PartnerShipmentCustomsInvoiceExport;
use App\Models\Dispatching\PartnerShipment;
use App\Models\Procurement\OrgPartner;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportPartnerShipmentCustomsInvoice extends OrgAction
{
    use WithProcurementAuthorisation;

    public function handle(PartnerShipment $partnerShipment, string $split): BinaryFileResponse
    {
        $export = new PartnerShipmentCustomsInvoiceExport($partnerShipment, $split);

        return Excel::download($export, $export->reference().'.xlsx');
    }

    public function asController(Organisation $organisation, OrgPartner $orgPartner, PartnerShipment $partnerShipment, string $split, ActionRequest $request): BinaryFileResponse
    {
        abort_unless(
            $orgPartner->organisation_id === $organisation->id
            && $partnerShipment->org_partner_id === $orgPartner->id
            && in_array($split, $partnerShipment->splits, true),
            404
        );
        $this->initialisation($organisation, $request);

        return $this->handle($partnerShipment, $split);
    }
}
