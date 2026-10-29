<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-10-29 10:40
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Procurement\OrgSupplierProducts;

use App\Actions\OrgAction;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Actions\SupplyChain\SupplierProduct\PdfSupplierProductLabels;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\Response;

class PdfOrgSupplierProductLabels extends OrgAction
{
    use WithAgentOrganisation;
    use WithProcurementAuthorisation;

    /**
     * @param  list<int>  $orgSupplierProductIds
     * @return list<int>
     */
    public function supplierProductIds(Organisation $organisation, array $orgSupplierProductIds): array
    {
        $agent = $this->getOrganisationAgent($organisation);

        return OrgSupplierProduct::whereIn('id', $orgSupplierProductIds)
            ->when(
                $agent,
                fn ($query) => $query->whereIn('org_agent_id', OrgAgent::where('agent_id', $agent->id)->select('id')),
                fn ($query) => $query->where('organisation_id', $organisation->id)
            )
            ->distinct()
            ->pluck('supplier_product_id')
            ->all();
    }

    /**
     * @return list<int>
     */
    public function labelOrganisationIds(Organisation $organisation): array
    {
        $agent = $this->getOrganisationAgent($organisation);

        return $agent ? OrgAgent::where('agent_id', $agent->id)->pluck('organisation_id')->all() : [$organisation->id];
    }

    public function rules(): array
    {
        return [
            ...Arr::except(PdfSupplierProductLabels::make()->rules(), 'supplier_products'),
            'org_supplier_products' => ['required', 'string', 'regex:/^\d+(,\d+)*$/'],
        ];
    }

    /**
     * @throws \Mpdf\MpdfException
     */
    public function asController(Organisation $organisation, ActionRequest $request): Response
    {
        $this->initialisation($organisation, $request);

        $orgSupplierProductIds = PdfSupplierProductLabels::supplierProductIds($this->validatedData['org_supplier_products']);
        abort_if(count($orgSupplierProductIds) > PdfSupplierProductLabels::MAX_PRODUCTS, 422, __('Select at most :count products', ['count' => PdfSupplierProductLabels::MAX_PRODUCTS]));

        $level = $this->validatedData['level'];
        $pdf   = PdfSupplierProductLabels::make()->handle(
            $organisation->group,
            $this->supplierProductIds($organisation, $orgSupplierProductIds),
            $level,
            $this->validatedData['for_organisation'] ?? null,
            $this->validatedData['size'] ?? null,
            (int) ($this->validatedData['copies'] ?? 1),
            $this->labelOrganisationIds($organisation)
        );

        return response($pdf, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="'.PdfSupplierProductLabels::make()->filename($level).'"');
    }
}
