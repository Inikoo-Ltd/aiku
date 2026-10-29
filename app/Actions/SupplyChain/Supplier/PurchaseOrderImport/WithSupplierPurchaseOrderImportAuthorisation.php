<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-10-29 10:57
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\SupplyChain\Supplier\PurchaseOrderImport;

use App\Models\SupplyChain\Supplier;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;

/**
 * Buyers order for their own organisations: the import is open to whoever can edit the purchase
 * orders of at least one organisation buying from the supplier, and the plan leaves out the rest.
 */
trait WithSupplierPurchaseOrderImportAuthorisation
{
    private Supplier $supplier;

    public function authorize(ActionRequest $request): bool
    {
        return static::canImportPurchaseOrders($request->user(), $this->supplier);
    }

    public static function canImportPurchaseOrders(User $user, Supplier $supplier): bool
    {
        return $user->authTo('supply-chain.view')
            && $supplier->orgSuppliers()->pluck('organisation_id')->contains(fn (int $organisationId) => $user->authTo("procurement.$organisationId.edit"));
    }
}
