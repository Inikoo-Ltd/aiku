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
use Illuminate\Support\Facades\DB;
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
     * The agent fee of a container, each supplier's own fee percentage of its goods, else the client's (2 unless set), written to the ledger
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

        $goodsByPercentage = self::goodsByFeePercentage($agentInvoice);
        $amount            = round(array_sum(array_map(fn (array $goods) => $goods['amount'] * $goods['percentage'] / 100, $goodsByPercentage)), 2);

        if ($autoFee) {
            $autoFee->update(['amount' => $amount, 'notes' => self::feeNote($goodsByPercentage, $agentInvoice)]);

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
            'notes'             => self::feeNote($goodsByPercentage, $agentInvoice),
            'data'              => ['agent_fee_of_invoice' => $agentInvoice->id],
        ]);
    }

    /**
     * @return list<string>
     */
    public static function feeCalculationExplained(): array
    {
        return [
            __('The agent fee is worked out when the invoice of a container is issued, on the goods in that container.'),
            __("The goods of each supplier take that supplier's agent fee. A supplier without one takes the client's agent fee, and a client without one takes :percentage%.", ['percentage' => self::DEFAULT_AGENT_FEE_PERCENTAGE]),
            __('The fee is one Agent fee line per container in the ledger. With suppliers at different fees its note shows each part.'),
            __('Changing a fee does not change invoices already issued. Issue the invoice again while the container is still at the agent to apply it; once the container is dispatched its fee stays.'),
            __('An agent fee recorded by hand for a container replaces the one worked out.'),
        ];
    }

    public static function clientFeePercentage(int $agentId, int $organisationId): float
    {
        $percentage = OrgAgent::where('agent_id', $agentId)->where('organisation_id', $organisationId)->value('agent_fee_percentage');

        return $percentage !== null ? (float) $percentage : self::DEFAULT_AGENT_FEE_PERCENTAGE;
    }

    /**
     * The invoice's goods grouped by the fee charged on them: a supplier's own agent fee, else the client's.
     *
     * @return list<array{percentage: float, amount: float}>
     */
    private static function goodsByFeePercentage(AgentInvoice $agentInvoice): array
    {
        $clientPercentage = self::clientFeePercentage($agentInvoice->agent_id, $agentInvoice->organisation_id);
        $lines            = collect($agentInvoice->lines ?? []);

        $supplierPercentages = DB::table('stock_delivery_items')
            ->join('supplier_products', 'supplier_products.id', '=', 'stock_delivery_items.supplier_product_id')
            ->join('suppliers', 'suppliers.id', '=', 'supplier_products.supplier_id')
            ->whereIn('stock_delivery_items.id', $lines->pluck('stock_delivery_item_id')->filter()->all())
            ->whereNotNull('suppliers.agent_fee_percentage')
            ->pluck('suppliers.agent_fee_percentage', 'stock_delivery_items.id');

        return $lines
            ->groupBy(fn (array $line) => (string) (float) ($supplierPercentages->get(Arr::get($line, 'stock_delivery_item_id')) ?? $clientPercentage))
            ->map(fn ($group, string $percentage) => ['percentage' => (float) $percentage, 'amount' => (float) $group->sum('amount')])
            ->sortByDesc('amount')
            ->values()
            ->all();
    }

    /**
     * @param  list<array{percentage: float, amount: float}>  $goodsByPercentage
     */
    private static function feeNote(array $goodsByPercentage, AgentInvoice $agentInvoice): string
    {
        $formatPercentage = fn (float $percentage) => rtrim(rtrim(number_format($percentage, 2), '0'), '.');

        if (count($goodsByPercentage) <= 1) {
            return __(':percentage% of the goods of :reference', [
                'percentage' => $formatPercentage($goodsByPercentage[0]['percentage'] ?? self::clientFeePercentage($agentInvoice->agent_id, $agentInvoice->organisation_id)),
                'reference'  => $agentInvoice->reference,
            ]);
        }

        return __(':parts of the goods of :reference', [
            'parts'     => implode(', ', array_map(
                fn (array $goods) => __(':percentage% of :amount', ['percentage' => $formatPercentage($goods['percentage']), 'amount' => number_format($goods['amount'], 2)]),
                $goodsByPercentage
            )),
            'reference' => $agentInvoice->reference,
        ]);
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
