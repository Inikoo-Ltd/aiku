<?php

namespace App\Actions\Procurement\OrgSupplierProducts;

use App\Actions\OrgAction;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Actions\SupplyChain\SupplierProduct\UpdateSupplierProduct;
use App\Actions\Traits\Authorisations\WithProcurementEditAuthorisation;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\SupplyChain\SupplierProduct;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;

class UpdateOrgSupplierProductCartonWeights extends OrgAction
{
    use WithAgentOrganisation;
    use WithProcurementEditAuthorisation;

    public function handle(OrgSupplierProduct $orgSupplierProduct, array $modelData): SupplierProduct
    {
        return UpdateSupplierProduct::make()->action($orgSupplierProduct->supplierProduct, $modelData);
    }

    public function rules(): array
    {
        return [
            'carton_net_weight' => ['present', 'nullable', 'integer', 'min:0'],
            'carton_weight'     => ['present', 'nullable', 'integer', 'min:0'],
        ];
    }

    public function asController(Organisation $organisation, OrgSupplierProduct $orgSupplierProduct, ActionRequest $request): SupplierProduct
    {
        $this->initialisation($organisation, $request);
        $this->authorizeProcurementRecord($orgSupplierProduct);

        return $this->handle($orgSupplierProduct, $this->validatedData);
    }
}
