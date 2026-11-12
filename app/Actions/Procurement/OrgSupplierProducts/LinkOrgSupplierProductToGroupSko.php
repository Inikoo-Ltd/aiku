<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-12 13:54
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Procurement\OrgSupplierProducts;

use App\Actions\Inventory\OrgStockHasOrgSupplierProduct\AttachOrgSupplierProductToOrgStock;
use App\Actions\OrgAction;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Actions\Traits\Authorisations\WithProcurementEditAuthorisation;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockHasOrgSupplierProduct;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * Links a supplier product with no SKO in its organisation to the organisation's SKO of the stock the supplier
 * product is already linked to at group level. A supplier product created for the SKO of one organisation is mirrored
 * to every organisation of the supplier, but only that organisation's SKO is linked, so the others show "No SKO" and
 * moving a purchase order line to that supplier tries to create the product again (HCBS-12 of PPD in AW).
 *
 * TODO: a one-off command should link every org supplier product in this state (3,596 on 12 Nov 2026), and
 * AttachOrgSupplierProductToOrgStock should link the SKOs of the same stock in the other organisations when it links
 * one, so this button is no longer needed.
 */
class LinkOrgSupplierProductToGroupSko extends OrgAction
{
    use WithAgentOrganisation;
    use WithProcurementEditAuthorisation;

    public function handle(OrgSupplierProduct $orgSupplierProduct): OrgStockHasOrgSupplierProduct
    {
        $orgStocks = static::groupSkosQuery($orgSupplierProduct)->limit(2)->get();

        if ($orgStocks->count() !== 1) {
            throw ValidationException::withMessages([
                'org_stock' => $orgStocks->isEmpty()
                    ? __(':code is not linked to a stock that has an SKO in this organisation: link it from the SKO page', ['code' => $orgSupplierProduct->supplierProduct->code])
                    : __(':code matches more than one SKO in this organisation: link it from the SKO page', ['code' => $orgSupplierProduct->supplierProduct->code]),
            ]);
        }

        return AttachOrgSupplierProductToOrgStock::make()->action($orgStocks->first(), $orgSupplierProduct);
    }

    /**
     * @return Builder<OrgStock>
     */
    public static function groupSkosQuery(OrgSupplierProduct $orgSupplierProduct): Builder
    {
        return OrgStock::where('organisation_id', $orgSupplierProduct->organisation_id)
            ->whereIn('stock_id', fn ($query) => $query->from('stock_has_supplier_products')
                ->where('supplier_product_id', $orgSupplierProduct->supplier_product_id)
                ->select('stock_id'));
    }

    public function asController(Organisation $organisation, OrgSupplierProduct $orgSupplierProduct, ActionRequest $request): RedirectResponse
    {
        $this->initialisation($organisation, $request);
        $this->authorizeProcurementRecord($orgSupplierProduct);
        $this->handle($orgSupplierProduct);

        return back();
    }
}
