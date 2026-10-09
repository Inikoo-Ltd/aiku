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
 * from what the delivery recorded: its lines as the goods, its shipping as freight and its extra costs as other
 * charges. An invoice already there, estimated or not, is left alone, and the delivery's own costs are never touched.
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

        $lines = StoreAgentInvoice::invoiceLines($stockDelivery);

        $charges = array_values(array_filter([
            ['description' => __('Freight'), 'type' => 'freight', 'amount' => round((float) $stockDelivery->cost_shipping, 2)],
            ['description' => __('Other costs'), 'type' => 'other', 'amount' => round((float) $stockDelivery->cost_extra, 2)],
        ], fn (array $charge) => $charge['amount'] > 0));

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
