<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Tue, 01 Apr 2026
 * Copyright (c) 2026, Inikoo LTD
 */

namespace App\Actions\Inventory\OrganisationStockHistory\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithInventoryAuthorisation;
use App\Actions\Traits\WithExportData;
use App\Exports\Inventory\ShowOrganisationStockHistoryExport;
use App\Models\Inventory\OrganisationStockHistory;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportShowOrganisationStockHistory extends OrgAction
{
    use WithInventoryAuthorisation;
    use WithExportData;

    public function handle(OrganisationStockHistory $organisationStockHistory, array $filters): BinaryFileResponse
    {
        $export = new ShowOrganisationStockHistoryExport($organisationStockHistory, $filters['tab'] ?? 'org_stocks');

        return $this->export($export, 'stock-history-'.$organisationStockHistory->date->format('Y-m-d'), $filters['type'] ?? 'xlsx');
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, OrganisationStockHistory $organisationStockHistory, ActionRequest $request): BinaryFileResponse
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($organisationStockHistory, [
            'type' => $request->input('type', 'xlsx'),
            'tab'  => $request->input('tab', 'org_stocks'),
        ]);
    }
}
