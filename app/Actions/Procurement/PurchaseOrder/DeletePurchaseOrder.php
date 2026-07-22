<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 17 Apr 2023 10:48:24 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrder;

use App\Actions\OrgAction;
use App\Actions\Procurement\OrgAgent\Hydrators\OrgAgentHydratePurchaseOrders;
use App\Actions\Procurement\OrgSupplier\Hydrators\OrgSupplierHydratePurchaseOrders;
use App\Actions\SupplyChain\Agent\Hydrators\AgentHydratePurchaseOrders;
use App\Actions\SupplyChain\Supplier\Hydrators\SupplierHydratePurchaseOrders;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydratePurchaseOrders;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydratePurchaseOrders;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\PurchaseOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class DeletePurchaseOrder extends OrgAction
{
    private PurchaseOrder $purchaseOrder;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("procurement.{$this->organisation->id}.edit");
    }

    public function afterValidator(Validator $validator): void
    {
        if ($this->purchaseOrder->state !== PurchaseOrderStateEnum::IN_PROCESS) {
            $validator->errors()->add('state', __('You can not delete this purchase order with state :state', ['state' => $this->purchaseOrder->state->value]));
        }
    }

    public function handle(PurchaseOrder $purchaseOrder): bool
    {
        /** @var OrgSupplier|OrgAgent|OrgPartner $parent */
        $parent = $purchaseOrder->parent;

        $purchaseOrder->purchaseOrderTransactions()->delete();
        $purchaseOrderDeleted = $purchaseOrder->delete();

        if (class_basename($parent) == 'OrgSupplier') {
            OrgSupplierHydratePurchaseOrders::dispatch($parent);
            SupplierHydratePurchaseOrders::dispatch($parent->supplier);
        } elseif (class_basename($parent) == 'OrgAgent') {
            OrgAgentHydratePurchaseOrders::dispatch($parent);
            AgentHydratePurchaseOrders::dispatch($parent->agent);
        }

        GroupHydratePurchaseOrders::dispatch($purchaseOrder->group);
        OrganisationHydratePurchaseOrders::dispatch($purchaseOrder->organisation);

        return $purchaseOrderDeleted;
    }

    public function asController(PurchaseOrder $purchaseOrder, ActionRequest $request): bool
    {
        $this->purchaseOrder = $purchaseOrder;
        $this->initialisation($purchaseOrder->organisation, $request);

        return $this->handle($purchaseOrder);
    }

    public function action(PurchaseOrder $purchaseOrder): bool
    {
        $this->asAction      = true;
        $this->purchaseOrder = $purchaseOrder;
        $this->initialisation($purchaseOrder->organisation, []);

        return $this->handle($purchaseOrder);
    }

    public function htmlResponse(bool $result, ActionRequest $request): RedirectResponse
    {
        return redirect()->route('grp.org.procurement.purchase_orders.index', [
            'organisation' => $request->route('purchaseOrder')->organisation->slug,
        ]);
    }
}
