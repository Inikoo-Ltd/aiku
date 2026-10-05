<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 24 Apr 2023 20:23:18 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrder;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Actions\Traits\WithExportData;
use App\Exports\Procurement\PurchaseOrdersExport;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportPurchaseOrders extends OrgAction
{
    use WithProcurementAuthorisation;
    use WithExportData;

    /**
     * @throws \Throwable
     */
    public function handle(Organisation $organisation, array $modelData): BinaryFileResponse
    {
        return $this->export(new PurchaseOrdersExport($organisation), 'purchase-orders', $modelData['type']);
    }

    /**
     * @throws \Throwable
     */
    public function asController(Organisation $organisation, ActionRequest $request): BinaryFileResponse
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation, $this->validatedData);
    }
}
