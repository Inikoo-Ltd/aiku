<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-10-29 10:40
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Procurement\OrgSupplierProducts\Json;

use App\Actions\Inventory\OrgStock\UI\GetOrgStockLabelOptions;
use App\Actions\Inventory\OrgStock\UI\PdfOrgStockLabel;
use App\Actions\OrgAction;
use App\Actions\Procurement\OrgSupplierProducts\PdfOrgSupplierProductLabels;
use App\Actions\SupplyChain\SupplierProduct\PdfSupplierProductLabels;
use App\Actions\Traits\Authorisations\WithAgentProductsAuthorisation;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;

class GetOrgSupplierProductLabelsPlan extends OrgAction
{
    use WithAgentProductsAuthorisation;

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
            'org_supplier_products' => ['required', 'string', 'regex:/^\d+(,\d+)*$/'],
            'for_organisation'      => ['sometimes', 'nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function asController(Organisation $organisation, ActionRequest $request): array
    {
        $this->initialisation($organisation, $request);

        $orgSupplierProductIds = PdfSupplierProductLabels::supplierProductIds($this->validatedData['org_supplier_products']);
        abort_if(count($orgSupplierProductIds) > PdfSupplierProductLabels::MAX_PRODUCTS, 422, __('Select at most :count products', ['count' => PdfSupplierProductLabels::MAX_PRODUCTS]));

        $orgLabels = PdfOrgSupplierProductLabels::make();

        return [
            ...PdfSupplierProductLabels::make()->plan(
                $organisation->group,
                $orgLabels->supplierProductIds($organisation, $orgSupplierProductIds),
                $this->validatedData['for_organisation'] ?? null,
                $orgLabels->labelOrganisationIds($organisation)
            ),
            'sizes'        => GetOrgStockLabelOptions::make()->getSizes(),
            'default_size' => PdfOrgStockLabel::DEFAULT_SIZE,
            'max_copies'   => PdfSupplierProductLabels::MAX_COPIES,
            'max_labels'   => PdfSupplierProductLabels::MAX_LABELS,
        ];
    }
}
