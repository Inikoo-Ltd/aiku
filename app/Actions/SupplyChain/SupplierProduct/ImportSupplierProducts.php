<?php

/*
 * author Arya Permana - Kirin
 * created on 18-02-2025-16h-22m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\SupplyChain\SupplierProduct;

use App\Actions\GrpAction;
use App\Actions\Helpers\Upload\ImportUpload;
use App\Actions\Helpers\Upload\StoreUpload;
use App\Actions\Traits\WithImportModel;
use App\Imports\SupplyChain\SupplierProductImport;
use App\Models\Helpers\Upload;
use App\Models\SupplyChain\Supplier;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\ActionRequest;

class ImportSupplierProducts extends GrpAction
{
    use WithImportModel;

    public function handle(Supplier $supplier, $file, array $modelData): Upload
    {
        $upload = StoreUpload::make()->fromFile(
            $supplier->group,
            $file,
            [
                'model' => 'SupplierProduct',
                'parent_type' => $supplier->getMorphClass(),
                'parent_id' => $supplier->id,
            ]
        );

        if ($this->isSync) {
            ImportUpload::run(
                $file,
                new SupplierProductImport($supplier, $upload)
            );
            $upload->refresh();
        } else {
            ImportUpload::dispatch(
                $this->tmpPath.$upload->filename,
                new SupplierProductImport($supplier, $upload)
            );
        }

        return $upload;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx,csv,xls,txt'],
        ];
    }

    public function asController(Supplier $supplier, ActionRequest $request): Upload
    {
        $this->initialisation($supplier->group, $request);

        $file = $request->file('file');
        Storage::disk('local')->put($this->tmpPath, $file);

        return $this->handle($supplier, $file, $this->validatedData);
    }

    public function runImportForCommand($file, $command): Upload
    {
        if ($supplierSlug = $command->argument('supplier')) {
            $supplier = Supplier::where('slug', $supplierSlug)->first();
        }

        return $this->handle($supplier, $file, []);
    }

    public string $commandSignature = 'supplier-products:import {--g|g_drive} {filename} {supplier?}';
}
