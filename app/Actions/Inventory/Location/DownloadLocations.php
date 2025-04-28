<?php

/*
 * author Arya Permana - Kirin
 * created on 24-02-2025-13h-09m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Inventory\Location;

use App\Actions\OrgAction;
use App\Models\Inventory\Warehouse;
use Lorisleiva\Actions\ActionRequest;
use App\Models\SysAdmin\Organisation;
use Maatwebsite\Excel\Facades\Excel;

class DownloadLocations extends OrgAction
{
    /**
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function handle(Warehouse $warehouse, array $modelData): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $fileName = 'locations_warehouse_'.$warehouse->id.'.xlsx';

        return Excel::download(new LocationsExport($warehouse, $modelData), $fileName);
    }

    /**
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $this->initialisationFromWarehouse($warehouse, $request);
        $columns = explode(',', $request->query('columns', ''));

        return $this->handle($warehouse, $columns);
    }
}
