<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Sep 2026 14:00:00 British Summer Time, Sheffield, UK
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrder;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Exports\Procurement\PurchaseOrderTransactionsExport;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportPurchaseOrderTransactions extends OrgAction
{
    use WithProcurementAuthorisation;

    public function handle(PurchaseOrder $purchaseOrder): BinaryFileResponse
    {
        return Excel::download(
            new PurchaseOrderTransactionsExport($purchaseOrder),
            preg_replace('/[^A-Za-z0-9._-]/', '-', $purchaseOrder->reference).'.xlsx'
        );
    }

    public function asController(Organisation $organisation, PurchaseOrder $purchaseOrder, ActionRequest $request): BinaryFileResponse
    {
        abort_unless($purchaseOrder->organisation_id === $organisation->id, 404);
        $this->initialisation($organisation, $request);

        return $this->handle($purchaseOrder);
    }
}
