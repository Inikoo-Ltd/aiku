<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 24 Apr 2023 20:23:18 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithInventoryAuthorisation;
use App\Actions\Traits\WithExportData;
use App\Exports\Inventory\OrgStocksExport;
use App\Models\Inventory\OrgStockFamily;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportOrgStocks extends OrgAction
{
    use WithInventoryAuthorisation;
    use WithExportData;

    /**
     * @throws \Throwable
     */
    public function handle(Organisation|OrgStockFamily $parent, array $modelData): BinaryFileResponse
    {
        return $this->export(new OrgStocksExport($parent), 'skos', $modelData['type']);
    }

    /**
     * @throws \Throwable
     */
    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): BinaryFileResponse
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($organisation, $this->validatedData);
    }

    /**
     * @throws \Throwable
     * @noinspection PhpUnusedParameterInspection
     */
    public function inStockFamily(Organisation $organisation, Warehouse $warehouse, OrgStockFamily $orgStockFamily, ActionRequest $request): BinaryFileResponse
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($orgStockFamily, $this->validatedData);
    }
}
