<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Tue, 28 Apr 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Dispatching\BatchCode;

use App\Actions\Helpers\Upload\ImportUpload;
use App\Actions\Helpers\Upload\StoreUpload;
use App\Actions\Traits\WithImportModel;
use App\Imports\Dispatching\BatchCodeImport;
use App\Models\Helpers\Upload;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;

class ImportBatchCodes
{
    use WithImportModel;

    public string $commandSignature = 'batch-code:import {warehouse} {--g|g_drive} {filename}';

    public function handle(Warehouse $warehouse, $file): Upload
    {
        $upload = StoreUpload::make()->fromFile(
            $warehouse->group,
            $file,
            [
                'model'       => 'BatchCode',
                'parent_type' => $warehouse->getMorphClass(),
                'parent_id'   => $warehouse->id,
            ]
        );

        ImportUpload::run(
            $file,
            new BatchCodeImport($warehouse, $upload)
        );
        $upload->refresh();

        return $upload;
    }

    public function runImportForCommand($file, $command): Upload
    {
        $warehouse = Warehouse::where('slug', $command->argument('warehouse'))->firstOrFail();

        return $this->handle($warehouse, $file);
    }

    public function inWarehouse(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): Upload
    {
        $file = $request->file('file');

        return $this->handle($warehouse, $file);
    }
}
