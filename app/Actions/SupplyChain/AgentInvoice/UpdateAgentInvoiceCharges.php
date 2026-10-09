<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\AgentInvoice;

use App\Actions\OrgAction;
use App\Models\SupplyChain\AgentInvoice;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * Replaces the charges an agent adds to a container's invoice, while the container is still at the agent.
 */
class UpdateAgentInvoiceCharges extends OrgAction
{
    public const string CHARGE_FREIGHT = 'freight';

    public const string CHARGE_OTHER = 'other';

    /**
     * @param  array{charges: array<int, array{description: string, type?: string, amount: float|string}>}  $modelData
     *
     * @throws ValidationException
     */
    public function handle(AgentInvoice $agentInvoice, array $modelData): AgentInvoice
    {
        if (!in_array($agentInvoice->stockDelivery->state, StoreAgentInvoice::OPEN_STATES, true)) {
            throw ValidationException::withMessages(['charges' => __('The container has left the agent, its invoice can no longer change.')]);
        }

        $charges = collect(Arr::get($modelData, 'charges', []))
            ->map(fn (array $charge) => [
                'description' => trim($charge['description']),
                'type'        => $charge['type'] ?? self::CHARGE_OTHER,
                'amount'      => round((float) $charge['amount'], 2),
            ])
            ->values()
            ->all();

        $chargesAmount = round(array_sum(array_column($charges, 'amount')), 2);

        $agentInvoice->update([
            'charges'        => $charges,
            'charges_amount' => $chargesAmount,
            'total_amount'   => round((float) $agentInvoice->goods_amount + $chargesAmount, 2),
        ]);

        return $agentInvoice;
    }

    public function rules(): array
    {
        return [
            'charges'               => ['present', 'array', 'max:50'],
            'charges.*.description' => ['required', 'string', 'max:255'],
            'charges.*.type'        => ['sometimes', 'string', 'in:'.self::CHARGE_FREIGHT.','.self::CHARGE_OTHER],
            'charges.*.amount'      => ['required', 'numeric', 'min:0', 'max:999999999999'],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("procurement.{$this->organisation->id}.edit");
    }

    /**
     * @throws ValidationException
     */
    public function asController(Organisation $organisation, AgentInvoice $agentInvoice, ActionRequest $request): AgentInvoice
    {
        abort_unless($organisation->agent?->id === $agentInvoice->agent_id, 404);
        $this->initialisation($organisation, $request);

        return $this->handle($agentInvoice, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
