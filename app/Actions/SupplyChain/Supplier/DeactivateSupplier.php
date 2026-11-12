<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\Supplier;

use App\Actions\Procurement\OrgSupplier\UpdateOrgSupplier;
use App\Actions\SupplyChain\Agent\Hydrators\AgentHydrateSuppliers;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Models\SupplyChain\Supplier;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Takes a supplier out of use in every organisation, once nothing is still being ordered from it. The record
 * and its history stay.
 */
class DeactivateSupplier
{
    use AsAction;

    public function handle(Supplier $supplier): Supplier
    {
        $openOrders = $supplier->purchaseOrders()
            ->whereIn('state', PurchaseOrderStateEnum::live())
            ->pluck('reference');

        if ($openOrders->isNotEmpty()) {
            throw ValidationException::withMessages([
                'supplier' => __('The supplier still has open purchase orders: :references', ['references' => $openOrders->implode(', ')]),
            ]);
        }

        $supplier->update(['status' => false, 'archived_at' => now()]);

        foreach ($supplier->orgSuppliers()->where('status', true)->get() as $orgSupplier) {
            UpdateOrgSupplier::make()->action($orgSupplier, ['status' => false]);
        }

        if ($supplier->agent) {
            AgentHydrateSuppliers::dispatch($supplier->agent);
        }

        return $supplier;
    }
}
