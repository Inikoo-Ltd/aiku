<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Aug 2026 23:10:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\AspoDeposit;

use App\Actions\OrgAction;
use App\Enums\SupplyChain\AspoDeposit\AspoDepositStateEnum;
use App\Models\SupplyChain\AgentSupplierPurchaseOrder;
use App\Models\SupplyChain\AspoDeposit;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

class StoreAspoDeposit extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        if (str_starts_with($request->route()->getName(), 'grp.org.')) {
            return $request->user()->authTo("procurement.{$this->organisation->id}.edit");
        }

        return $request->user()->authTo('supply-chain.edit');
    }

    public function rules(): array
    {
        return [
            'reference'   => ['sometimes', 'nullable', 'string', 'max:255'],
            'amount'      => ['required', 'numeric', 'gt:0'],
            'currency_id' => ['sometimes', 'nullable', 'exists:currencies,id'],
            'notes'       => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function handle(AgentSupplierPurchaseOrder $agentSupplierPurchaseOrder, array $modelData): AspoDeposit
    {
        return $agentSupplierPurchaseOrder->deposits()->create(
            array_merge($modelData, [
                'group_id'    => $agentSupplierPurchaseOrder->group_id,
                'agent_id'    => $agentSupplierPurchaseOrder->supplier?->agent_id,
                'currency_id' => $modelData['currency_id'] ?? $agentSupplierPurchaseOrder->currency_id,
                'state'       => AspoDepositStateEnum::PENDING,
            ])
        );
    }

    public function asController(AgentSupplierPurchaseOrder $agentSupplierPurchaseOrder, ActionRequest $request): AspoDeposit
    {
        $this->initialisationFromGroup($agentSupplierPurchaseOrder->group, $request);

        return $this->handle($agentSupplierPurchaseOrder, $this->validatedData);
    }

    public function action(AgentSupplierPurchaseOrder $agentSupplierPurchaseOrder, array $modelData): AspoDeposit
    {
        $this->asAction = true;
        $this->initialisationFromGroup($agentSupplierPurchaseOrder->group, $modelData);

        return $this->handle($agentSupplierPurchaseOrder, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return redirect()->back();
    }

}
