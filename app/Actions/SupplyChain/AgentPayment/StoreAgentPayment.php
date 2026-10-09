<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\AgentPayment;

use App\Actions\OrgAction;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Models\GoodsIn\StockDelivery;
use App\Models\SupplyChain\AgentPayment;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

/**
 * Records a payment one of our organisations made to an agent for a container without a deposit request.
 */
class StoreAgentPayment extends OrgAction
{
    use WithAgentOrganisation;

    /**
     * @param  array{date: string, amount: float|string, reference?: string|null, notes?: string|null}  $modelData
     */
    public function handle(StockDelivery $stockDelivery, array $modelData): AgentPayment
    {
        return $stockDelivery->agentPayments()->create([
            'group_id'        => $stockDelivery->group_id,
            'agent_id'        => $stockDelivery->agent_id,
            'organisation_id' => $stockDelivery->organisation_id,
            'currency_id'     => $stockDelivery->currency_id,
            'date'            => $modelData['date'],
            'amount'          => round((float) $modelData['amount'], 2),
            'reference'       => $modelData['reference'] ?? null,
            'notes'           => $modelData['notes'] ?? null,
            'created_by'      => $this->asAction ? null : request()->user()?->id,
        ]);
    }

    public function rules(): array
    {
        return [
            'date'      => ['required', 'date'],
            'amount'    => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'reference' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes'     => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("procurement.{$this->organisation->id}.edit");
    }

    public function asController(Organisation $organisation, StockDelivery $stockDelivery, ActionRequest $request): AgentPayment
    {
        abort_unless($stockDelivery->agent_id && $organisation->agent?->id === $stockDelivery->agent_id, 404);
        $this->initialisation($organisation, $request);

        return $this->handle($stockDelivery, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
