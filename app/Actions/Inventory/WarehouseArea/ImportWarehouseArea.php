<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 18 Sep 2023 18:48:13 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\WarehouseArea;

use App\Actions\Helpers\Upload\ImportUpload;
use App\Actions\Helpers\Upload\StoreUpload;
use App\Actions\Traits\WithImportModel;
use App\Imports\Warehouse\WarehouseAreaImport;
use App\Models\Helpers\Upload;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\ActionRequest;

class ImportWarehouseArea
{
    use WithImportModel;

    public function handle(Warehouse $warehouse, $file): Upload
    {
        $upload = StoreUpload::make()->fromFile(
            $warehouse->group,
            $file,
            [
                'model' => 'WarehouseArea',
                'parent_type' => $warehouse->getMorphClass(),
                'parent_id' => $warehouse->id,
            ]
        );

        if ($this->isSync) {
            ImportUpload::run(
                $file,
                new WarehouseAreaImport($warehouse, $upload)
            );
            $upload->refresh();
        } else {
            ImportUpload::dispatch(
                $this->tmpPath.$upload->filename,
                new WarehouseAreaImport($warehouse, $upload)
            );
        }

        return $upload;
    }

    public function runImportForCommand($file, $command): Upload
    {
        $warehouse = Warehouse::where('slug', $command->argument('warehouse'))->first();

        if (!$warehouse) {
            throw new \Exception('Warehouse not found');
        }

        return $this->handle($warehouse, $file);
    }

    public function inWarehouse(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): Upload
    {
        $file = $request->file('file');
        Storage::disk('local')->put($this->tmpPath, $file);

        return $this->handle($warehouse, $file);
    }

    public string $commandSignature = 'warehouse-area:import {warehouse} {--g|g_drive} {filename}';

}
