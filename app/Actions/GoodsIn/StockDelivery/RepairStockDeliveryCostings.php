<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Aug 2026 22:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDelivery;

use App\Enums\GoodsIn\StockDelivery\StockDeliveryCostTypeEnum;
use App\Models\GoodsIn\StockDelivery;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairStockDeliveryCostings
{
    use AsAction;

    public string $commandSignature = 'stock_delivery:repair_costings {--dry-run}';

    public string $commandDescription = 'Create stock delivery cost checklist rows for old deliveries from their legacy cost columns';

    public function handle(StockDelivery $stockDelivery): void
    {
        $receivedAt = $stockDelivery->placed_at ?? $stockDelivery->checked_at ?? $stockDelivery->received_at;

        $rows = [
            [StockDeliveryCostTypeEnum::AGENT_INVOICE, $stockDelivery->cost_items],
            [StockDeliveryCostTypeEnum::SHIPPING, $stockDelivery->cost_shipping],
            [StockDeliveryCostTypeEnum::DUTY, $stockDelivery->cost_duties],
            [StockDeliveryCostTypeEnum::EXTRA, $stockDelivery->cost_extra],
        ];

        foreach ($rows as [$type, $amount]) {
            $hasAmount = (float) $amount > 0;

            if ($type === StockDeliveryCostTypeEnum::EXTRA && !$hasAmount) {
                continue;
            }

            $stockDelivery->costs()->create([
                'group_id'        => $stockDelivery->group_id,
                'organisation_id' => $stockDelivery->organisation_id,
                'type'            => $type,
                'amount'          => $hasAmount ? $amount : null,
                'received_at'     => $hasAmount || $type === StockDeliveryCostTypeEnum::AGENT_INVOICE ? $receivedAt : null,
                'is_na'           => !$hasAmount && $type !== StockDeliveryCostTypeEnum::AGENT_INVOICE && $stockDelivery->is_costed,
            ]);
        }
    }

    public function asCommand(Command $command): int
    {
        $query = StockDelivery::whereDoesntHave('costs')
            ->whereNotNull('placed_at');

        $count = $query->count();
        $command->info("Stock deliveries to repair: $count");

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
