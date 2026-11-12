<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\AgentPayment;

use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrder\SyncPurchaseOrderPaidFromAgentLedger;
use App\Enums\SupplyChain\AspoDeposit\DepositRequestStateEnum;
use App\Actions\SupplyChain\AgentInvoice\SyncAgentInvoiceChargesFromLedger;
use App\Models\SupplyChain\AgentPayment;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class DeleteAgentPayment extends OrgAction
{
    private AgentPayment $agentPayment;

    public function handle(AgentPayment $agentPayment): AgentPayment
    {
        $paidDatesBefore = $agentPayment->type->isSupplierPayment() ? SyncPurchaseOrderPaidFromAgentLedger::ledgerDatesFor([$agentPayment->purchase_order_id]) : [];

        DB::transaction(function () use ($agentPayment) {
            $agentPayment->delete();

            $depositRequest = $agentPayment->depositRequest()->lockForUpdate()->first();
            if ($depositRequest?->state === DepositRequestStateEnum::SETTLED
                && (float) $depositRequest->transfers()->sum('amount') < (float) $depositRequest->items()->sum('amount') - 0.005) {
                $depositRequest->update(['state' => DepositRequestStateEnum::REQUESTED, 'settled_at' => null]);
                $depositRequest->items()->update(['paid_at' => null]);
            }
        });

        if ($agentPayment->stockDelivery) {
            SyncAgentInvoiceChargesFromLedger::run($agentPayment->stockDelivery);
        }

        SyncPurchaseOrderPaidFromAgentLedger::runForOrders($paidDatesBefore);

        return $agentPayment;
    }

    public function authorize(ActionRequest $request): bool
    {
        return self::canDelete($this->agentPayment, $request->user());
    }

    /**
     * Each side removes only its own lines: a payment the client recorded (against a container or a money request)
     * by the client's purchaser or accountant, every other line of the ledger by the agent's staff. A transfer sent
     * against a money request is never removed: the agent was emailed its bank statement and the request is paid by it.
     */
    public static function canDelete(AgentPayment $agentPayment, User $user): bool
    {
        if ($agentPayment->deposit_request_id !== null) {
            return false;
        }

        if ($agentPayment->isRecordedByClient()) {
            return $user->authTo(["procurement.$agentPayment->organisation_id.edit", "accounting.$agentPayment->organisation_id.edit"]);
        }

        $agentOrganisationId = $agentPayment->agent?->organisation_id;

        return $agentOrganisationId !== null && $user->authTo("procurement.$agentOrganisationId.edit");
    }

    public function asController(AgentPayment $agentPayment, ActionRequest $request): AgentPayment
    {
        $this->agentPayment = $agentPayment;
        $this->initialisation($agentPayment->organisation, $request);

        return $this->handle($agentPayment);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
