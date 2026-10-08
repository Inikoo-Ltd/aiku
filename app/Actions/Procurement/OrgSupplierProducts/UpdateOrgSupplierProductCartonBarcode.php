<?php

namespace App\Actions\Procurement\OrgSupplierProducts;

use App\Actions\Inventory\OrgStock\UpdateOrgStock;
use App\Actions\OrgAction;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Actions\Traits\Authorisations\WithProcurementEditAuthorisation;
use App\Models\Inventory\OrgStock;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;

class UpdateOrgSupplierProductCartonBarcode extends OrgAction
{
    use WithAgentOrganisation;
    use WithProcurementEditAuthorisation;

    public function handle(OrgStock $orgStock, ?string $cartonBarcode): OrgStock
    {
        return UpdateOrgStock::make()->action($orgStock, ['carton_barcode' => $cartonBarcode]);
    }

    public function rules(): array
    {
        return [
            'carton_barcode' => ['present', 'nullable', 'string', 'max:64', 'regex:/^[\x20-\x7E]+$/'],
        ];
    }

    public function asController(Organisation $organisation, OrgSupplierProduct $orgSupplierProduct, OrgStock $orgStock, ActionRequest $request): OrgStock
    {
        $this->initialisation($organisation, $request);
        $this->authorizeProcurementRecord($orgSupplierProduct);

        abort_unless($orgSupplierProduct->supplierProduct->stocks()->where('stocks.id', $orgStock->stock_id)->exists(), 404);

        return $this->handle($orgStock, $this->validatedData['carton_barcode']);
    }
}
