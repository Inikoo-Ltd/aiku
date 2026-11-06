<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-02 13:31
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\SupplyChain\SupplierShareLink\UI;

use App\Actions\Inventory\OrgStock\UI\GetOrgStockLabelOptions;
use App\Actions\Inventory\OrgStock\UI\PdfOrgStockLabel;
use App\Actions\SupplyChain\SupplierProduct\Json\GetSupplierProductLabelsPlan;
use App\Actions\SupplyChain\SupplierProduct\PdfSupplierProductLabels;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Which of the products the supplier ticked can be printed at each level, before it prints their barcode labels.
 */
class GetSupplierPortalLabelsPlan
{
    use AsAction;

    public function rules(): array
    {
        return GetSupplierProductLabelsPlan::make()->rules();
    }

    public function asController(string $token, ActionRequest $request): JsonResponse
    {
        $supplier  = PdfSupplierPortalLabels::supplier($token);
        $validated = $request->validated();

        return response()->json([
            ...PdfSupplierProductLabels::make()->plan(
                $supplier->group,
                PdfSupplierPortalLabels::supplierProductIds($supplier, $validated['supplier_products']),
                $validated['for_organisation'] ?? null,
                PdfSupplierPortalLabels::allowedOrganisationIds($supplier)
            ),
            'sizes'        => GetOrgStockLabelOptions::make()->getSizes(),
            'default_size' => PdfOrgStockLabel::DEFAULT_SIZE,
            'max_copies'   => PdfSupplierProductLabels::MAX_COPIES,
            'max_labels'   => PdfSupplierProductLabels::MAX_LABELS,
        ]);
    }
}
