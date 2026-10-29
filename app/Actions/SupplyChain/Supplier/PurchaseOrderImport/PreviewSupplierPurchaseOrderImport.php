<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-10-29 10:57
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\SupplyChain\Supplier\PurchaseOrderImport;

use App\Actions\OrgAction;
use App\Models\SupplyChain\Supplier;
use Illuminate\Http\UploadedFile;
use Lorisleiva\Actions\ActionRequest;

class PreviewSupplierPurchaseOrderImport extends OrgAction
{
    use WithSupplierPurchaseOrderImportAuthorisation;

    /**
     * @return array<string, mixed>
     */
    public function handle(Supplier $supplier, UploadedFile $file): array
    {
        return PlanSupplierPurchaseOrderImport::make()->handle($supplier, $file, request()->user());
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx,xls'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function asController(Supplier $supplier, ActionRequest $request): array
    {
        $this->supplier = $supplier;
        $this->initialisationFromGroup($supplier->group, $request);

        return $this->handle($supplier, $this->validatedData['file']);
    }
}
