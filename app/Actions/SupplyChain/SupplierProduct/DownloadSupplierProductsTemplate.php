<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 06 Aug 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\SupplyChain\SupplierProduct;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithSupplyChainEditAuthorisation;
use App\Exports\SupplyChain\SupplierProductTemplateExport;
use App\Models\SupplyChain\Supplier;
use Lorisleiva\Actions\ActionRequest;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DownloadSupplierProductsTemplate extends OrgAction
{
    use WithSupplyChainEditAuthorisation;

    public function handle(): BinaryFileResponse
    {
        return Excel::download(new SupplierProductTemplateExport(), 'supplier_products_template.xlsx');
    }

    public function asController(Supplier $supplier, ActionRequest $request): BinaryFileResponse
    {
        $this->initialisationFromGroup($supplier->group, $request);

        return $this->handle();
    }
}
