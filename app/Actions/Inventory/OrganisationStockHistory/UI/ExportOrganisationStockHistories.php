<?php

/*
 * Author: Nickel
 * Created: Tue, 01 Apr 2026
 * Copyright (c) 2026, Inikoo LTD
 */

namespace App\Actions\Inventory\OrganisationStockHistory\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithInventoryAuthorisation;
use App\Actions\Traits\WithExportData;
use App\Exports\Inventory\OrganisationStockHistoriesExport;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportOrganisationStockHistories extends OrgAction
{
    use WithInventoryAuthorisation;
    use WithExportData;

    public function handle(Organisation $organisation, array $filters): BinaryFileResponse
    {
        $export = new OrganisationStockHistoriesExport($organisation, $filters);

        return $this->export($export, 'stock-histories', $filters['type'] ?? 'xlsx');
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): BinaryFileResponse
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        $filters = [
            'type'    => $request->input('type', 'xlsx'),
            'buckets' => $request->input('elements.bucket'),
        ];

        return $this->handle($organisation, $filters);
    }
}
