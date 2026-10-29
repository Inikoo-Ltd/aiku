<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-10-29 09:44
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\SupplyChain\SupplierProduct\Json;

use App\Actions\Inventory\OrgStock\UI\GetOrgStockLabelOptions;
use App\Actions\Inventory\OrgStock\UI\PdfOrgStockLabel;
use App\Actions\OrgAction;
use App\Actions\SupplyChain\SupplierProduct\PdfSupplierProductLabels;
use App\Actions\Traits\Authorisations\WithSupplyChainAuthorisation;
use Lorisleiva\Actions\ActionRequest;

class GetSupplierProductLabelsPlan extends OrgAction
{
    use WithSupplyChainAuthorisation;

    /**
     * @return array<string, mixed>
     */
    public function jsonResponse(array $plan): array
    {
        return $plan;
    }

    public function rules(): array
    {
        return [
            'supplier_products' => ['required', 'string', 'regex:/^\d+(,\d+)*$/'],
            'for_organisation'  => ['sometimes', 'nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function asController(ActionRequest $request): array
    {
        $this->initialisationFromGroup(app('group'), $request);

        $supplierProductIds = PdfSupplierProductLabels::supplierProductIds($this->validatedData['supplier_products']);
        abort_if(count($supplierProductIds) > PdfSupplierProductLabels::MAX_PRODUCTS, 422, __('Select at most :count products', ['count' => PdfSupplierProductLabels::MAX_PRODUCTS]));

        $labelOptions = GetOrgStockLabelOptions::make();

        return [
            ...PdfSupplierProductLabels::make()->plan($this->group, $supplierProductIds, $this->validatedData['for_organisation'] ?? null),
            'sizes'        => $labelOptions->getSizes(),
            'default_size' => PdfOrgStockLabel::DEFAULT_SIZE,
            'max_copies'   => PdfSupplierProductLabels::MAX_COPIES,
            'max_labels'   => PdfSupplierProductLabels::MAX_LABELS,
        ];
    }
}
