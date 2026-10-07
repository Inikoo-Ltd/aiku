<?php

/*
 * author Arya Permana - Kirin
 * created on 18-02-2025-16h-22m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\SupplyChain\SupplierProduct;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithSupplyChainEditAuthorisation;
use App\Actions\Helpers\Upload\StoreUpload;
use App\Actions\Traits\WithImportModel;
use App\Actions\SupplyChain\SupplierProduct\Upload\PrepareSupplierProductUpload;
use App\Models\Helpers\Upload;
use App\Models\SupplyChain\Supplier;
use Lorisleiva\Actions\ActionRequest;

class ImportSupplierProducts extends OrgAction
{
    use WithSupplyChainEditAuthorisation;
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

        return PrepareSupplierProductUpload::run($supplier, $upload)->refresh();
    }

    public function rules(): array
    {
        return [
            'file'             => ['required', 'file', 'mimes:xlsx,csv,xls,txt'],
        ];
    }


    public function asController(Supplier $supplier, ActionRequest $request): Upload
    {
        $this->initialisationFromGroup($supplier->group, $request);

        return $this->handle($supplier, $request->file('file'), $this->validatedData);
    }

    /**
     * @return array{id: int, preview_url: string}
     */
    public function jsonResponse(Upload $upload): array
    {
        return [
            'id'          => $upload->id,
            'preview_url' => route('grp.supply-chain.suppliers.supplier_products.uploads.show', ['supplier' => $upload->parent->slug, 'upload' => $upload->id]),
        ];
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
