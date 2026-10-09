<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\AgentInvoice\UI;

use App\Actions\SupplyChain\AgentInvoice\ApproveAgentInvoiceCharges;
use App\Actions\SupplyChain\AgentInvoice\StoreAgentInvoice;
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

        return [
            'invoice'              => $invoice ? $this->invoiceData($organisation, $invoice) : null,
            'payments'             => $stockDelivery->agentPayments()->orderBy('date')->get()->map(fn (AgentPayment $payment) => [
                'id'            => $payment->id,
                'date'          => $payment->date->toDateString(),
                'amount'        => (float) $payment->amount,
                'reference'     => $payment->reference,
                'notes'         => $payment->notes,
            ])->values()->all(),
            'is_open'              => in_array($stockDelivery->state, StoreAgentInvoice::OPEN_STATES, true),
            'store_route'          => [
                'name'       => 'grp.org.agent.agent_invoices.store',
                'parameters' => [$organisation->slug, $stockDelivery->slug],
            ],
            'charges_update_route' => $invoice ? [
                'name'       => 'grp.org.agent.agent_invoices.charges.update',
                'parameters' => [$organisation->slug, $invoice->id],
            ] : null,
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
            'charges'          => $invoice->charges ?? [],
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
}
