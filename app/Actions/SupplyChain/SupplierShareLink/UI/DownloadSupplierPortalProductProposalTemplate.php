<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-18 08:52
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\SupplyChain\SupplierShareLink\UI;

use App\Exports\SupplyChain\SupplierProductProposalTemplateExport;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierShareLink;
use Lorisleiva\Actions\Concerns\AsAction;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The spreadsheet a supplier fills with its new product ideas, its currency already in place, to upload from the
 * Suggest a product form.
 */
class DownloadSupplierPortalProductProposalTemplate
{
    use AsAction;

    public function handle(Supplier $supplier): BinaryFileResponse
    {
        return Excel::download(new SupplierProductProposalTemplateExport($supplier->currency?->code), 'new-products-'.$supplier->slug.'.xlsx');
    }

    public function asController(string $token): BinaryFileResponse
    {
        $link = SupplierShareLink::findLiveByToken($token);
        abort_if($link->needsCode(), 403);

        return $this->handle($link->supplier);
    }
}
