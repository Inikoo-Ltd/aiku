<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 26 Sept 2026 09:30:00 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Inventory\LocationOrgStock;

use App\Actions\DevOps\MonitorStockLocationIntegrity;
use App\Actions\Inventory\LocationOrgStock\AddToLocationOrgStockQuantity;
use App\Actions\Inventory\LocationOrgStock\GetLocationOrgStockQuantity;
use App\Models\Inventory\LocationOrgStock;
use App\Models\Inventory\OrgStockMovement;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * HELP-3418: puts back on each location the quantity its last audit plus movements say it holds.
 * The movements are what pickers and receivers actually did, the location quantity is only a
 * running total of them that concurrent writers could overwrite, so the total is what gets
 * corrected. No movement is written and no money moves; an audit movement would claim a count
 * nobody made and shift the stock valuation.
 */
class RepairLocationOrgStockQuantityFromMovements
{
    use AsAction;

    public string $commandSignature = 'repair:location_org_stock_quantity_from_movements {--o|organisation= : Organisation slug} {--days=3650 : Locations touched in this many days} {--apply : Write the corrections, otherwise only list them}';

    public string $commandDescription = 'Set location stock quantities back to their last audit plus stock movements';

    /**
     * @return array{0: float, 1: float}|null location quantity before and after, null when skipped
     */
    public function handle(int $locationOrgStockId, bool $apply): ?array
    {
        return DB::transaction(function () use ($locationOrgStockId, $apply) {
            $locationOrgStock = LocationOrgStock::lockForUpdate()->find($locationOrgStockId);
            if (!$locationOrgStock) {
                return null;
            }

            $movementInFlight = OrgStockMovement::where('org_stock_id', $locationOrgStock->org_stock_id)
                ->where('location_id', $locationOrgStock->location_id)
                ->where('created_at', '>', now()->subMinutes(5))
                ->exists();
            if ($movementInFlight) {
                return null;
            }

            $before   = (float)$locationOrgStock->quantity;
            $expected = GetLocationOrgStockQuantity::run($locationOrgStock->orgStock, $locationOrgStock->location);

            if (abs($before - $expected) <= 0.001) {
                return null;
            }

            if ($apply) {
                AddToLocationOrgStockQuantity::run($locationOrgStock, $expected - $before);
            }

            return [$before, $expected];
        });
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $organisationId = null;
        if ($slug = $command->option('organisation')) {
            $organisationId = Organisation::where('slug', $slug)->value('id');
            if (!$organisationId) {
                $command->error("Organisation $slug not found");

                return 1;
            }
        }

        $apply = (bool)$command->option('apply');
        $rows  = MonitorStockLocationIntegrity::make()->locationsOutOfStep((int)$command->option('days'), $organisationId);

        $table = [];
        foreach ($rows as $row) {
            $result = $this->handle($row->location_org_stock_id, $apply);
            if ($result === null) {
                $table[] = [$row->organisation, $row->code, $row->location, $row->quantity, $row->expected, '', 'skipped'];
                continue;
            }
            [$before, $expected] = $result;
            $table[] = [$row->organisation, $row->code, $row->location, $before, $expected, round($expected - $before, 6), $apply ? 'repaired' : 'dry run'];
        }

        $command->table(['Organisation', 'SKO', 'Location', 'Quantity', 'Movements', 'Change', 'Result'], $table);
        $command->info(count($rows).' locations out of step'.($apply ? '' : ', nothing written: pass --apply to repair'));

        return 0;
    }
}
