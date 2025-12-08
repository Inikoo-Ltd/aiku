<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 17 Apr 2023 10:48:24 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrder;

use App\Actions\Procurement\OrgAgent\Hydrators\OrgAgentHydratePurchaseOrders;
use App\Actions\Procurement\OrgSupplier\Hydrators\OrgSupplierHydratePurchaseOrders;
use App\Actions\SupplyChain\Agent\Hydrators\AgentHydratePurchaseOrders;
use App\Actions\SupplyChain\Supplier\Hydrators\SupplierHydratePurchaseOrders;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydratePurchaseOrders;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Http\Resources\Procurement\PurchaseOrderResource;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\PurchaseOrder;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class DeletePurchaseOrder
{
    use AsAction;
    use WithAttributes;

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(PurchaseOrder $purchaseOrder): bool
    {

        if ((in_array($purchaseOrder->state, [PurchaseOrderStateEnum::IN_PROCESS, PurchaseOrderStateEnum::SUBMITTED]))) {
            /** @var OrgSupplier|OrgAgent|OrgPartner $parent */
            $parent = $purchaseOrder->parent;

            $purchaseOrder->purchaseOrderTransactions()->delete();
            $purchaseOrderDeleted = $purchaseOrder->delete();

            if (class_basename($parent) == 'OrgSupplier') {
                OrgSupplierHydratePurchaseOrders::dispatch($parent);
                SupplierHydratePurchaseOrders::dispatch($parent->supplier);
            } else {
                OrgAgentHydratePurchaseOrders::dispatch($parent);
                AgentHydratePurchaseOrders::dispatch($parent->agent);
            }

            OrganisationHydratePurchaseOrders::dispatch($purchaseOrder->organisation);

            return $purchaseOrderDeleted;
        }

        throw ValidationException::withMessages([
            'purchase_order' => 'You can not delete this purchase order with state '.$purchaseOrder->state->value,
        ]);
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function action(PurchaseOrder $purchaseOrder): bool
    {
        return $this->handle($purchaseOrder);
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function asController(PurchaseOrder $purchaseOrder): bool
    {
        return $this->handle($purchaseOrder);
    }

    public function jsonResponse(PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        return new PurchaseOrderResource($purchaseOrder);
    }
}
