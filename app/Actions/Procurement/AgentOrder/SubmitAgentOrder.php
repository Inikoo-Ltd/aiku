<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 Oct 2026 19:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\AgentOrder;

use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrder\DeletePurchaseOrder;
use App\Actions\Procurement\PurchaseOrder\SendPurchaseOrderToSupplier;
use App\Actions\Procurement\PurchaseOrder\UpdatePurchaseOrderStateToSubmitted;
use App\Actions\Traits\Authorisations\WithProcurementEditAuthorisation;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\PurchaseOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * Submits every supplier order of the agent order at once and sends the agent one message with all
 * of them, as submitting the single org-agent order used to. Supplier orders left without products
 * are deleted rather than submitted empty.
 */
class SubmitAgentOrder extends OrgAction
{
    use WithProcurementEditAuthorisation;

    /**
     * @return Collection<int, PurchaseOrder>
     */
    public function handle(OrgAgent $orgAgent, string $agentOrderReference, ?string $sendVia = null): Collection
    {
        $drafts = PurchaseOrder::inAgentOrder($orgAgent->organisation_id, $orgAgent->agent_id, $agentOrderReference)
            ->where('state', PurchaseOrderStateEnum::IN_PROCESS)
            ->withCount('purchaseOrderTransactions')
            ->orderBy('reference')
            ->get();

        [$withProducts, $empty] = $drafts->partition(fn (PurchaseOrder $purchaseOrder) => $purchaseOrder->purchase_order_transactions_count > 0);

        if ($withProducts->isEmpty()) {
            throw ValidationException::withMessages(['agent_order' => __('Add products before submitting :reference', ['reference' => $agentOrderReference])]);
        }

        $empty->each(fn (PurchaseOrder $purchaseOrder) => DeletePurchaseOrder::make()->action($purchaseOrder));

        $submitted = $withProducts->map(fn (PurchaseOrder $purchaseOrder) => UpdatePurchaseOrderStateToSubmitted::make()->action($purchaseOrder))->values();

        if ($sendVia && in_array($sendVia, array_column(SendPurchaseOrderToSupplier::channels($submitted->first()), 'channel'), true)) {
            SendAgentOrderToAgent::dispatch($submitted->pluck('id')->all(), $agentOrderReference, $sendVia);
        }

        return $submitted;
    }

    public function rules(): array
    {
        return [
            'agent_order_reference' => ['required', 'string'],
            'send_via'              => ['sometimes', 'nullable', 'string', 'in:email,whatsapp'],
        ];
    }

    public function asController(OrgAgent $orgAgent, ActionRequest $request): Collection
    {
        $this->initialisation($orgAgent->organisation, $request);

        return $this->handle($orgAgent, $this->validatedData['agent_order_reference'], Arr::get($this->validatedData, 'send_via'));
    }

    public function action(OrgAgent $orgAgent, string $agentOrderReference, ?string $sendVia = null): Collection
    {
        $this->asAction = true;
        $this->initialisation($orgAgent->organisation, ['agent_order_reference' => $agentOrderReference, 'send_via' => $sendVia]);

        return $this->handle($orgAgent, $agentOrderReference, $sendVia);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
