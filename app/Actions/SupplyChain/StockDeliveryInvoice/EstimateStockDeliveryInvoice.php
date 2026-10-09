<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\StockDeliveryInvoice;

use App\Actions\SupplyChain\AgentInvoice\StoreAgentInvoice;
use App\Enums\SupplyChain\StockDeliveryInvoice\StockDeliveryInvoiceSourceEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\SupplyChain\AgentInvoice;
use App\Models\SupplyChain\SupplierInvoice;
use Illuminate\Console\Command;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Deliveries fetched from Aurora never had their paper invoice entered, so the agent or supplier invoice is estimated
 * from what the delivery recorded: the goods only, at the goods cost Aurora recorded on the delivery, spread over its
 * lines in proportion to their order amounts (or at the order amounts when Aurora recorded none). Shipping, extras and
 * duties stay the receiving organisation's landed costs: they were paid in its own country and are not on the
 * agent's or supplier's invoice. An invoice already there is left alone, and the delivery's costs are never touched.
 */
class EstimateStockDeliveryInvoice
{
    use AsAction;

    public string $commandSignature = 'stock_delivery:estimate_invoices {--dry-run}';

    public function handle(StockDelivery $stockDelivery): AgentInvoice|SupplierInvoice|null
    {
        if ($stockDelivery->agentInvoice()->exists() || $stockDelivery->supplierInvoice()->exists()) {
            return null;
        }

        $lines   = $this->scaledToRecordedGoodsCost(StoreAgentInvoice::invoiceLines($stockDelivery), (float) $stockDelivery->cost_items);
        $charges = [];

        $goodsAmount   = round(array_sum(array_column($lines, 'amount')), 2);
        $chargesAmount = round(array_sum(array_column($charges, 'amount')), 2);

        $invoiceData = [
            'group_id'          => $stockDelivery->group_id,
            'organisation_id'   => $stockDelivery->organisation_id,
            'stock_delivery_id' => $stockDelivery->id,
            'source'            => StockDeliveryInvoiceSourceEnum::ESTIMATED,
            'reference'         => $stockDelivery->data['invoice_number'] ?? $stockDelivery->reference,
            'date'              => ($stockDelivery->dispatched_at ?? $stockDelivery->received_at ?? $stockDelivery->date)->toDateString(),
            'currency_id'       => $stockDelivery->currency_id,
            'number_lines'      => count($lines),
            'goods_amount'      => $goodsAmount,
            'charges_amount'    => $chargesAmount,
            'total_amount'      => round($goodsAmount + $chargesAmount, 2),
            'lines'             => $lines,
            'charges'           => $charges,
        ];

        if ($stockDelivery->agent_id) {
            return AgentInvoice::create($invoiceData + ['agent_id' => $stockDelivery->agent_id]);
        }

        return SupplierInvoice::create($invoiceData + ['supplier_id' => $stockDelivery->supplier_id]);
    }

    /**
     * @param  array<int, array{quantity: float, unit_price: float, amount: float}>  $lines
     * @return array<int, array{quantity: float, unit_price: float, amount: float}>
     */
    private function scaledToRecordedGoodsCost(array $lines, float $recordedGoodsCost): array
    {
        $orderAmount = array_sum(array_column($lines, 'amount'));

        if ($recordedGoodsCost <= 0 || $orderAmount <= 0 || abs($recordedGoodsCost - $orderAmount) < 0.01) {
            return $lines;
        }

        $factor = $recordedGoodsCost / $orderAmount;
        foreach ($lines as $index => $line) {
            $lines[$index]['amount'] = round($line['amount'] * $factor, 2);
        }

        $largest                    = array_search(max(array_column($lines, 'amount')), array_column($lines, 'amount'));
        $lines[$largest]['amount'] = round($lines[$largest]['amount'] + round($recordedGoodsCost, 2) - array_sum(array_column($lines, 'amount')), 2);

        foreach ($lines as $index => $line) {
            $lines[$index]['unit_price'] = $line['quantity'] > 0 ? round($line['amount'] / $line['quantity'], 4) : 0.0;
        }

        return $lines;
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $query = StockDelivery::whereNotNull('source_id')
            ->whereDoesntHave('agentInvoice')
            ->whereDoesntHave('supplierInvoice');

        $command->info('Aurora stock deliveries without an invoice: '.$query->count());

        if ($command->option('dry-run')) {
            return 0;
        }

        $query->chunkById(200, function ($stockDeliveries) use ($command) {
            foreach ($stockDeliveries as $stockDelivery) {
                $this->handle($stockDelivery);
            }
            $command->getOutput()->write('.');
        });

        $command->newLine();
        $command->info('Done');

        return 0;
    }
}
