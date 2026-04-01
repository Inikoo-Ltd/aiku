<?php

/*
 * Author: Nickel
 * Created: Tue, 01 Apr 2026
 * Copyright (c) 2026, Inikoo LTD
 */

namespace App\Actions\Inventory\OrgStock;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithInventoryAuthorisation;
use App\Actions\Traits\WithExportData;
use App\Exports\Inventory\OrgStockHistoryExport;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockFamily;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportOrgStockHistory extends OrgAction
{
    use WithInventoryAuthorisation;
    use WithExportData;

    private Organisation|OrgStockFamily $parent;

    public function handle(OrgStock $orgStock, array $filters): BinaryFileResponse
    {
        $export = new OrgStockHistoryExport($orgStock, $filters);

        return $this->export($export, 'stock-history-'.$orgStock->slug, $filters['type'] ?? 'xlsx');
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, OrgStock $orgStock, ActionRequest $request): BinaryFileResponse
    {
        $this->parent = $organisation;
        $this->initialisationFromWarehouse($warehouse, $request);

        $filters = [
            'type'    => $request->input('type', 'xlsx'),
            'between' => $request->input('between', []),
        ];

        return $this->handle($orgStock, $filters);
    }

    public function inStockFamily(Organisation $organisation, Warehouse $warehouse, OrgStockFamily $orgStockFamily, OrgStock $orgStock, ActionRequest $request): BinaryFileResponse
    {
        $this->parent = $orgStockFamily;
        $this->initialisationFromWarehouse($warehouse, $request);

        $filters = [
            'type'    => $request->input('type', 'xlsx'),
            'between' => $request->input('between', []),
        ];

        return $this->handle($orgStock, $filters);
    }
}
