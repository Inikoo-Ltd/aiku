<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-13 10:45
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\SupplyChain\Supplier;

use App\Actions\OrgAction;
use App\Models\SupplyChain\Supplier;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

/**
 * The agent sets the fee it charges on the goods of this supplier, in place of the fee it charges the client.
 * Empty goes back to the client's agent fee.
 */
class UpdateSupplierAgentFeePercentage extends OrgAction
{
    private Supplier $supplier;

    public function handle(Supplier $supplier, array $modelData): Supplier
    {
        $supplier->update(['agent_fee_percentage' => $modelData['agent_fee_percentage'] ?? null]);

        return $supplier;
    }

    public function rules(): array
    {
        return [
            'agent_fee_percentage' => ['present', 'nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        $agentOrganisationId = $this->supplier->agent?->organisation_id;

        return $agentOrganisationId !== null && $request->user()->authTo("procurement.$agentOrganisationId.edit");
    }

    public function asController(Supplier $supplier, ActionRequest $request): Supplier
    {
        $this->supplier = $supplier;
        abort_unless($supplier->agent, 404);
        $this->initialisation($supplier->agent->organisation, $request);

        return $this->handle($supplier, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
