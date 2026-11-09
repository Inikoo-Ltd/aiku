<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\AgentInvoice\UI;

use App\Actions\SupplyChain\AgentInvoice\ApproveAgentInvoiceCharges;
use App\Actions\SupplyChain\AgentInvoice\StoreAgentInvoice;
use App\Actions\SupplyChain\AgentInvoice\SyncAgentInvoiceChargesFromLedger;
use App\Actions\SupplyChain\AgentPayment\DeleteAgentPayment;
use App\Actions\SupplyChain\AgentPayment\StoreAgentPayment;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderPaymentMethodEnum;
use App\Enums\SupplyChain\AgentPayment\AgentPaymentTypeEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\SupplyChain\AgentInvoice;
use App\Models\SupplyChain\AgentPayment;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * What the agent sees of a container's invoice on the container page: the invoice if made, the payments taken off it,
 * and the routes to make it and change its charges. Payments are recorded by the paying organisation, the agent only
 * sees them.
 */
class GetAgentContainerInvoiceData
{
    use AsObject;

    public function handle(Organisation $organisation, StockDelivery $stockDelivery): array
    {
        $invoice = $stockDelivery->agentInvoice()->with('currency:id,code')->first();
        $user    = request()->user();

        return [
            'invoice'              => $invoice ? $this->invoiceData($organisation, $invoice) : null,
            'payments'             => $stockDelivery->agentPayments()->with('bankStatement:id,ulid')->orderBy('date')->get()->map(fn (AgentPayment $payment) => [
                'id'             => $payment->id,
                'date'           => $payment->date->toDateString(),
                'amount'         => (float) $payment->amount,
                'reference'      => $payment->reference,
                'notes'          => $payment->notes,
                'method'         => $payment->method?->labels()[$payment->method->value],
                'bank_statement' => $payment->bankStatement?->ulid,
                'delete_route'   => $user && DeleteAgentPayment::canDelete($payment, $user)
                    ? ['name' => 'grp.models.agent_payment.delete', 'parameters' => ['agentPayment' => $payment->id]]
                    : null,
            ])->values()->all(),
            'methods'              => PurchaseOrderPaymentMethodEnum::options(),
            'payment_store_route'  => $user && StoreAgentPayment::canRecord($stockDelivery, $user)
                ? ['name' => 'grp.models.stock-delivery.agent_payment.store', 'parameters' => ['stockDelivery' => $stockDelivery->id]]
                : null,
            'is_open'              => in_array($stockDelivery->state, StoreAgentInvoice::OPEN_STATES, true),
            'store_route'          => [
                'name'       => 'grp.org.agent.agent_invoices.store',
                'parameters' => [$organisation->slug, $stockDelivery->slug],
            ],
            'charge_form'          => $this->chargeForm($organisation, $stockDelivery, $invoice),
            'ledger_route'         => $organisation->agent ? [
                'name'       => 'grp.org.agent.accounting.ledger.index',
                'parameters' => ['organisation' => $organisation->slug, 'filter' => ['global' => $stockDelivery->reference]],
            ] : null,
        ];
    }

    /**
     * The agent adds a container's charges from its page as lines of its ledger, while the costing has not taken
     * them. The agent fee is left out: it is worked out from the goods.
     */
    private function chargeForm(Organisation $organisation, StockDelivery $stockDelivery, ?AgentInvoice $invoice): ?array
    {
        $user = request()->user();
        if (!$organisation->agent || !$invoice || !$user?->authTo("procurement.{$organisation->id}.edit")
            || !SyncAgentInvoiceChargesFromLedger::followsLedger($invoice) || !SyncAgentInvoiceChargesFromLedger::chargesOpen($stockDelivery)) {
            return null;
        }

        return [
            'store_route'       => ['name' => 'grp.models.agent.ledger_entry.store', 'parameters' => ['agent' => $organisation->agent->id]],
            'organisation_id'   => $stockDelivery->organisation_id,
            'stock_delivery_id' => $stockDelivery->id,
            'types'             => collect(AgentPaymentTypeEnum::cases())
                ->filter(fn (AgentPaymentTypeEnum $type) => $type->isCharge() && $type !== AgentPaymentTypeEnum::AGENT_FEE)
                ->map(fn (AgentPaymentTypeEnum $type) => ['value' => $type->value, 'label' => AgentPaymentTypeEnum::labels()[$type->value], 'needs_receipt' => $type->needsReceipt()])
                ->values()->all(),
        ];
    }

    private function invoiceData(Organisation $organisation, AgentInvoice $invoice): array
    {
        return [
            'id'               => $invoice->id,
            'reference'        => $invoice->reference,
            'date'             => $invoice->date->toDateString(),
            'currency_code'    => $invoice->currency->code,
            'goods_amount'     => (float) $invoice->goods_amount,
            'charges'          => $this->chargesData($invoice),
            'charges_amount'   => (float) $invoice->charges_amount,
            'charges_approved' => ApproveAgentInvoiceCharges::isApproved($invoice),
            'total_amount'     => (float) $invoice->total_amount,
            'number_lines'     => $invoice->number_lines,
            'advance_payments' => $invoice->advancePayments(),
            'paid_amount'      => $invoice->paidAmount(),
            'balance_due'      => $invoice->balanceDue(),
            'pdf_route'        => [
                'name'       => 'grp.org.agent.agent_invoices.pdf',
                'parameters' => [$organisation->slug, $invoice->id],
            ],
        ];
    }

    /**
     * A charge synced from the ledger shows its type (and SKO) apart from the agent's notes; a charge typed by hand
     * only has its description.
     *
     * @return array<int, array{description: string, label: string, notes: ?string, type: ?string, amount: float|null}>
     */
    private function chargesData(AgentInvoice $invoice): array
    {
        $charges     = $invoice->charges ?? [];
        $ledgerLines = AgentPayment::whereIn('id', array_filter(array_column($charges, 'ledger_id')))
            ->with('orgStock:id,code')
            ->get()
            ->keyBy('id');

        return array_map(function (array $charge) use ($ledgerLines) {
            $ledgerLine = $ledgerLines->get($charge['ledger_id'] ?? null);

            return [
                'description' => $charge['description'],
                'label'       => $ledgerLine
                    ? implode(' · ', array_filter([AgentPaymentTypeEnum::labels()[$ledgerLine->type->value], $ledgerLine->orgStock?->code]))
                    : $charge['description'],
                'notes'       => $ledgerLine?->notes,
                'type'        => $charge['type'] ?? null,
                'amount'      => $charge['amount'] ?? null,
            ];
        }, $charges);
    }
}
