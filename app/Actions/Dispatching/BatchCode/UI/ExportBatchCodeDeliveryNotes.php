<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 30 Apr 2026
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Dispatching\BatchCode\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithInventoryAuthorisation;
use App\Actions\Traits\WithExportData;
use App\Exports\Dispatching\BatchCodeDeliveryNotesExport;
use App\Models\Dispatching\BatchCode;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportBatchCodeDeliveryNotes extends OrgAction
{
    use WithInventoryAuthorisation;
    use WithExportData;

    public function handle(BatchCode $batchCode, array $modelData): BinaryFileResponse
    {
        $export = new BatchCodeDeliveryNotesExport($batchCode);

        $safeCode = str_replace(['/', '\\'], '-', $batchCode->code);

        return $this->export($export, 'batch-code-delivery-notes-'.$safeCode, $modelData['type'] ?? 'xlsx');
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, BatchCode $batchCode, ActionRequest $request): BinaryFileResponse
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($batchCode, $request->all());
    }
}
