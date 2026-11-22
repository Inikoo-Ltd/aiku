<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 19:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock;

use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateSkuValue;
use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateValueInLocations;
use App\Actions\Inventory\OrgStock\Stock\Concerns\CalculatesOrgStockHistories;
use App\Actions\Inventory\OrgStock\Stock\RebuildOrgStockHistoriesSince;
use App\Actions\Inventory\OrgStockMovement\CalculateOrgStockMovementRunningValues;
use App\Actions\Inventory\OrgStockMovement\StoreOrgStockMovement;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementReasonEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockMovement;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

/**
 * Sets what each unit of an SKO on hand is worth from $date on, without moving any stock: a
 * zero-quantity adjustment carrying the new cost, which the valuation walk applies to every unit
 * held at that moment. Backdate it to just after the movement that put the wrong price on the
 * stock, so the histories are rebuilt from there (HELP-4868). Stock used in between keeps the
 * amounts it was booked at.
 */
class RevalueOrgStock
{
    use AsAction;
    use CalculatesOrgStockHistories;

    public function handle(OrgStock $orgStock, float $costPerSku, Carbon $date, string $note, ?int $userId = null): OrgStockMovement
    {
        if ($costPerSku <= 0) {
            throw new RuntimeException('The cost per SKO must be above zero');
        }

        $locationOrgStock = $orgStock->locationOrgStocks()->with('location')->orderByDesc('quantity')->first();
        if (!$locationOrgStock) {
            throw new RuntimeException($orgStock->code.' is in no location');
        }

        $state       = $this->getValuationState($orgStock, $date);
        $onHand      = max((float)($state['onHand'] ?? 0), 0);
        $valueBefore = $state ? array_sum(array_map(fn ($layer) => $layer[0] * $layer[1], $state['layers'])) : 0;

        $movement = StoreOrgStockMovement::make()->action($orgStock, $locationOrgStock->location, [
            'type'       => OrgStockMovementTypeEnum::ADJUSTMENT->value,
            'reason'     => OrgStockMovementReasonEnum::REVALUATION->value,
            'quantity'   => 0,
            'org_amount' => round($onHand * $costPerSku - $valueBefore, 3),
            'note'       => $note,
            'user_id'    => $userId,
            'date'       => $date->format('Y-m-d H:i:s.u'),
        ]);
        $movement->update(['cost_per_sku' => $costPerSku]);

        CalculateOrgStockMovementRunningValues::run($orgStock->id);
        RebuildOrgStockHistoriesSince::run($orgStock->id, $date->toDateString());
        OrgStockHydrateSkuValue::run($orgStock->refresh());
        OrgStockHydrateValueInLocations::run($orgStock->refresh());

        return $movement->refresh();
    }

    public function getCommandSignature(): string
    {
        return 'org_stock:revalue {orgStock : OrgStock id} {cost : New cost per SKO in the organisation currency} {note} {--date= : Datetime the new cost applies from, default now}';
    }

    public function asCommand(Command $command): int
    {
        $orgStock = OrgStock::findOrFail((int)$command->argument('orgStock'));
        $date     = $command->option('date') ? Carbon::parse($command->option('date')) : now();

        $movement = $this->handle($orgStock, (float)$command->argument('cost'), $date, (string)$command->argument('note'));

        $orgStock->refresh();
        $command->info(sprintf('%s revalued from %s (movement %d): sku_value %s, value_in_locations %s', $orgStock->code, $date->toDateTimeString(), $movement->id, $orgStock->sku_value, $orgStock->value_in_locations));

        return 0;
    }
}
