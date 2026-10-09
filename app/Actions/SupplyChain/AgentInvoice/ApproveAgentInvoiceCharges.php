<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\AgentInvoice;

use App\Actions\OrgAction;
use App\Models\GoodsIn\StockDelivery;
use App\Models\SupplyChain\AgentInvoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * The agent's charges go into our stock cost, so the organisation paying them approves them first; until then the
 * container's costing waits. Approving a container already placed costs it from its invoice straight away.
 */
class ApproveAgentInvoiceCharges extends OrgAction
{
    public const string APPROVED_AT = 'charges_approved_at';

    public const string APPROVED_BY = 'charges_approved_by';

    /**
     * @throws ValidationException
     */
    public function handle(StockDelivery $stockDelivery, ?int $userId = null): AgentInvoice
    {
        /** @var AgentInvoice|null $agentInvoice */
        $agentInvoice = $stockDelivery->agentInvoice()->first();

        if (!$agentInvoice) {
            throw ValidationException::withMessages(['invoice' => __('This container has no agent invoice yet.')]);
        }

        $data = $agentInvoice->data ?? [];
        data_set($data, self::APPROVED_AT, now()->toIso8601String());
        data_set($data, self::APPROVED_BY, $userId);
        $agentInvoice->update(['data' => $data]);

        ApplyAgentInvoiceCosting::run($stockDelivery->refresh());

        return $agentInvoice;
    }

    public static function isApproved(AgentInvoice $agentInvoice): bool
    {
        return empty($agentInvoice->charges) || (bool) data_get($agentInvoice->data, self::APPROVED_AT);
    }

    public static function clearApproval(AgentInvoice $agentInvoice): void
    {
        $data = $agentInvoice->data ?? [];
        unset($data[self::APPROVED_AT], $data[self::APPROVED_BY]);
        $agentInvoice->data = $data;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo(["procurement.{$this->organisation->id}.edit", "accounting.{$this->organisation->id}.edit"]);
    }

    /**
     * @throws ValidationException
     */
    public function asController(StockDelivery $stockDelivery, ActionRequest $request): AgentInvoice
    {
        abort_unless($stockDelivery->agent_id, 404);
        $this->initialisation($stockDelivery->organisation, $request);

        return $this->handle($stockDelivery, $request->user()->id);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
