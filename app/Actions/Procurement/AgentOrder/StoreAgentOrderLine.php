<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 Oct 2026 19:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\AgentOrder;

use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrder\StorePurchaseOrder;
use App\Actions\Procurement\PurchaseOrderTransaction\StorePurchaseOrderTransaction;
use App\Actions\Traits\Authorisations\WithProcurementEditAuthorisation;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * A product added on the agent order goes on its supplier's order in that agent order, which is
 * created on the first product from that supplier.
 */
class StoreAgentOrderLine extends OrgAction
{
    use WithProcurementEditAuthorisation;

    public function handle(OrgAgent $orgAgent, string $agentOrderReference, OrgSupplierProduct $orgSupplierProduct, array $modelData): PurchaseOrderTransaction
    {
        return DB::transaction(function () use ($orgAgent, $agentOrderReference, $orgSupplierProduct, $modelData) {
            $fail = fn (string $message) => throw ValidationException::withMessages(['org_supplier_product' => $message]);

            if ($orgSupplierProduct->org_agent_id !== $orgAgent->id) {
                $fail(__('This product is not bought through :agent', ['agent' => $orgAgent->agent->name]));
            }

            $agentOrder = PurchaseOrder::inAgentOrder($orgAgent->organisation_id, $orgAgent->agent_id, $agentOrderReference);
            if ((clone $agentOrder)->where('state', '!=', PurchaseOrderStateEnum::IN_PROCESS)->exists()) {
                $fail(__(':reference has been submitted, new products go on the next agent order', ['reference' => $agentOrderReference]));
            }

            $orgSupplier   = $orgSupplierProduct->orgSupplier;
            $purchaseOrder = (clone $agentOrder)->where('parent_id', $orgSupplier->id)->lockForUpdate()->first();

            if (!$purchaseOrder) {
                $openDraft = $orgSupplier->purchaseOrders()->where('state', PurchaseOrderStateEnum::IN_PROCESS)->first();
                if ($openDraft && $openDraft->agent_order_reference && $openDraft->agent_order_reference !== $agentOrderReference) {
                    $fail(__(':supplier already has a draft in :reference, add the product there or submit it first', [
                        'supplier'  => $orgSupplier->supplier->code,
                        'reference' => $openDraft->agent_order_reference,
                    ]));
                }

                $purchaseOrder = $openDraft
                    ? tap($openDraft)->update(['agent_order_reference' => $agentOrderReference])
                    : StorePurchaseOrder::make()->action($orgSupplier, ['agent_order_reference' => $agentOrderReference]);
            }

            return StorePurchaseOrderTransaction::make()->addOrgSupplierProduct($purchaseOrder, $orgSupplierProduct, $modelData);
        });
    }

    public function rules(): array
    {
        return [
            'quantity_ordered' => ['required', 'numeric', 'gt:0'],
        ];
    }

    public function asController(OrgAgent $orgAgent, OrgSupplierProduct $orgSupplierProduct, ActionRequest $request): void
    {
        $this->initialisation($orgAgent->organisation, $request);

        $this->handle($orgAgent, (string) $request->query('agentOrderReference'), $orgSupplierProduct, $this->validatedData);
    }

    public function action(OrgAgent $orgAgent, string $agentOrderReference, OrgSupplierProduct $orgSupplierProduct, array $modelData): PurchaseOrderTransaction
    {
        $this->asAction = true;
        $this->initialisation($orgAgent->organisation, $modelData);

        return $this->handle($orgAgent, $agentOrderReference, $orgSupplierProduct, $this->validatedData);
    }
}
