<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 19 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\AgentInvoice;

use App\Enums\Procurement\SupplierAgreedTerm\SupplierAgreedTermTypeEnum;
use App\Enums\SupplyChain\StockDeliveryInvoice\StockDeliveryInvoiceSourceEnum;
use App\Enums\SupplyChain\AgentPayment\AgentPaymentTypeEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Procurement\OrgAgent;
use App\Models\SupplyChain\AgentInvoice;
use App\Models\SupplyChain\AgentPayment;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A container's invoice charges are the money the agent paid out for it, as recorded in its ledger: loading,
 * inland charges, packaging (of one SKO or of the whole container), inspections, samples, moulds, the agent fee
 * and other expenses. They follow the ledger until the costing takes them; a change sends them back for approval.
 */
class SyncAgentInvoiceChargesFromLedger
{
    use AsAction;

    public const float DEFAULT_AGENT_FEE_PERCENTAGE = 2;

    public function handle(StockDelivery $stockDelivery): ?AgentInvoice
    {
        /** @var AgentInvoice|null $agentInvoice */
        $agentInvoice = $stockDelivery->agentInvoice()->first();

        if (!$agentInvoice || !self::followsLedger($agentInvoice) || !self::chargesOpen($stockDelivery)) {
            return null;
        }

        self::dropAutomaticFeeWhenRecordedByHand($stockDelivery);

        $charges = self::ledgerLines($stockDelivery)
            ->map(fn (AgentPayment $line) => [
                'description'  => implode(' · ', array_filter([AgentPaymentTypeEnum::labels()[$line->type->value], $line->orgStock?->code, $line->notes])),
                'type'         => self::chargeType($line->type),
                'amount'       => round((float) $line->amount, 2),
                'ledger_id'    => $line->id,
                'org_stock_id' => $line->org_stock_id,
            ])
            ->values()
            ->all();

        if ($charges === ($agentInvoice->charges ?? [])) {
            return $agentInvoice;
        }

        ApproveAgentInvoiceCharges::clearApproval($agentInvoice);

        $chargesAmount = round(array_sum(array_column($charges, 'amount')), 2);
        $agentInvoice->update([
            'data'           => $agentInvoice->data,
            'charges'        => $charges,
            'charges_amount' => $chargesAmount,
            'total_amount'   => round((float) $agentInvoice->goods_amount + $chargesAmount, 2),
        ]);

        return $agentInvoice;
    }

    /**
     * A fee the agent recorded for the container (by hand or from its balance sheet) replaces the one worked out
     * when the invoice was issued, so the container is not charged twice.
     */
    private static function dropAutomaticFeeWhenRecordedByHand(StockDelivery $stockDelivery): void
    {
        $fees = AgentPayment::where('stock_delivery_id', $stockDelivery->id)->where('type', AgentPaymentTypeEnum::AGENT_FEE)->get();
        if ($fees->contains(fn (AgentPayment $fee) => !Arr::get($fee->data, 'agent_fee_of_invoice'))) {
            $fees->filter(fn (AgentPayment $fee) => Arr::get($fee->data, 'agent_fee_of_invoice'))->each(fn (AgentPayment $fee) => $fee->delete());
        }
    }

    /**
     * A container takes charges from the ledger until its costing takes them, also after it was dispatched.
     */
    public static function chargesOpen(StockDelivery $stockDelivery): bool
    {
        return !$stockDelivery->is_costed && !Arr::get($stockDelivery->data ?? [], ApplyAgentInvoiceCosting::APPLIED_AT);
    }

    /**
     * Only an invoice the agent issued in Aiku, in the agent's own currency, takes its charges from the ledger; one
     * our staff typed from the agent's paper invoice keeps the charges they typed.
     */
    public static function followsLedger(AgentInvoice $agentInvoice): bool
    {
        return $agentInvoice->source === StockDeliveryInvoiceSourceEnum::AGENT
            && $agentInvoice->currency_id === $agentInvoice->agent->organisation->currency_id;
    }

    /**
     * The agent fee of a container, its client's fee percentage (2 unless set) of the goods, written to the ledger
     * when the invoice is issued and kept in step when it is issued again. A fee the agent recorded by hand for
     * this container stands instead.
     */
    public static function recordAgentFee(AgentInvoice $agentInvoice): void
    {
        $stockDelivery = $agentInvoice->stockDelivery;
        $agent         = $agentInvoice->agent;

        if (!self::followsLedger($agentInvoice)) {
            return;
        }

        $fees    = AgentPayment::where('stock_delivery_id', $stockDelivery->id)->where('type', AgentPaymentTypeEnum::AGENT_FEE)->get();
        $autoFee = $fees->first(fn (AgentPayment $fee) => Arr::get($fee->data, 'agent_fee_of_invoice'));
        if ($fees->contains(fn (AgentPayment $fee) => !Arr::get($fee->data, 'agent_fee_of_invoice'))) {
            self::dropAutomaticFeeWhenRecordedByHand($stockDelivery);

            return;
        }

        $percentage = OrgAgent::where('agent_id', $agent->id)->where('organisation_id', $stockDelivery->organisation_id)->value('agent_fee_percentage');
        $percentage = $percentage !== null ? (float) $percentage : self::DEFAULT_AGENT_FEE_PERCENTAGE;
        $amount     = round((float) $agentInvoice->goods_amount * $percentage / 100, 2);

        if ($autoFee) {
            $autoFee->update(['amount' => $amount, 'notes' => self::feeNote($percentage, $agentInvoice)]);

            return;
        }

        if ($amount <= 0) {
            return;
        }

        AgentPayment::create([
            'group_id'          => $agent->group_id,
            'agent_id'          => $agent->id,
            'organisation_id'   => $stockDelivery->organisation_id,
            'stock_delivery_id' => $stockDelivery->id,
            'type'              => AgentPaymentTypeEnum::AGENT_FEE,
            'date'              => $agentInvoice->date->toDateString(),
            'amount'            => $amount,
            'currency_id'       => $agentInvoice->currency_id,
            'reference'         => $agentInvoice->reference,
            'notes'             => self::feeNote($percentage, $agentInvoice),
            'data'              => ['agent_fee_of_invoice' => $agentInvoice->id],
        ]);
    }

    private static function feeNote(float $percentage, AgentInvoice $agentInvoice): string
    {
        return __(':percentage% of the goods of :reference', ['percentage' => rtrim(rtrim(number_format($percentage, 2), '0'), '.'), 'reference' => $agentInvoice->reference]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, AgentPayment>
     */
    public static function ledgerLines(StockDelivery $stockDelivery): \Illuminate\Support\Collection
    {
        $notCharges = array_merge(AgentPaymentTypeEnum::moneyInValues(), AgentPaymentTypeEnum::supplierPaymentValues());

        return AgentPayment::where('stock_delivery_id', $stockDelivery->id)
            ->whereNotIn('type', $notCharges)
            ->with('orgStock:id,code')
            ->orderBy('date')
            ->orderBy('id')
            ->get();
    }

    public static function chargeType(AgentPaymentTypeEnum $type): string
    {
        return match ($type) {
            AgentPaymentTypeEnum::PACKAGING, AgentPaymentTypeEnum::CONTAINER_SUPPLIES => SupplierAgreedTermTypeEnum::PACKING_CHARGE->value,
            AgentPaymentTypeEnum::MOULD_OR_LOGO => SupplierAgreedTermTypeEnum::MOULD_OR_TOOLING_CHARGE->value,
            AgentPaymentTypeEnum::SAMPLES       => SupplierAgreedTermTypeEnum::SAMPLE_CHARGE->value,
            AgentPaymentTypeEnum::INLAND_CHARGE => SupplierAgreedTermTypeEnum::LOCAL_DELIVERY_TO_PORT_CHARGE->value,
            default                             => UpdateAgentInvoiceCharges::CHARGE_OTHER,
        };
    }
}
