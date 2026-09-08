<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 27 Jul 2026 16:00:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock;

use App\Actions\Inventory\LocationOrgStock\AuditLocationOrgStock;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementReasonEnum;
use App\Models\Goods\TradeUnit;
use App\Models\Inventory\OrgStock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Retires the IAL01 "Import Address Labels" org stocks once the label is no longer a component of
 * anything: writes the fake quantity down to zero and discontinues them.
 *
 * The quantity was never real, so it is written off through AuditLocationOrgStock with reason
 * `data_fix`, which records an org stock movement carrying the delta, the cost and a note. That
 * leaves a trail explaining where 961k units went, which a direct update on the quantity would not.
 *
 * Nothing is deleted. The org stocks stay as discontinued rows, and the delivery note items,
 * pickings and movements that record labels already shipped are untouched order history.
 *
 * The bills of materials must be clear first, otherwise zeroing the stock would strand products that
 * still list the label as a component, so the command refuses to run while any line remains.
 *
 * Delivery notes already carrying the label are left alone to finish normally, but they must finish
 * before the stock goes: a picker cannot pick a label out of a location holding none. The command
 * refuses to run while any undispatched delivery note still has a label left to pick.
 */
class RetireIal01OrgStocks
{
    use AsAction;

    private const string CONSUMABLE_CODE = 'IAL01';

    public string $commandSignature = 'inventory:retire-ial01-org-stocks
        {--apply : Write off and discontinue, without this the command only reports what it would do}';

    public function getTradeUnit(): ?TradeUnit
    {
        return TradeUnit::where('code', self::CONSUMABLE_CODE)->first();
    }

    /**
     * @return \Illuminate\Support\Collection<int, OrgStock>
     */
    public function getOrgStocks(TradeUnit $tradeUnit)
    {
        $ids = DB::table('model_has_trade_units')
            ->where('model_type', 'OrgStock')
            ->where('trade_unit_id', $tradeUnit->id)
            ->pluck('model_id');

        return OrgStock::whereIn('id', $ids)->with(['organisation', 'locationOrgStocks.location'])->get();
    }

    /**
     * Bill of materials lines still pointing at the label, which must be gone before the stock can be
     * retired.
     */
    public function countRemainingBomLines(TradeUnit $tradeUnit): int
    {
        return DB::table('model_has_trade_units')
            ->whereIn('model_type', ['Product', 'MasterAsset'])
            ->where('trade_unit_id', $tradeUnit->id)
            ->count();
    }

    /**
     * Label units on delivery notes that are still working through the warehouse, which a picker has
     * yet to take off the shelf.
     */
    public function countUnpickedLabels(TradeUnit $tradeUnit): float
    {
        $labelOrgStockIds = DB::table('model_has_trade_units')
            ->where('model_type', 'OrgStock')
            ->where('trade_unit_id', $tradeUnit->id)
            ->pluck('model_id');

        return (float) DB::table('delivery_note_items')
            ->join('delivery_notes', 'delivery_notes.id', '=', 'delivery_note_items.delivery_note_id')
            ->whereIn('delivery_note_items.org_stock_id', $labelOrgStockIds)
            ->whereNotIn('delivery_notes.state', ['dispatched', 'cancelled'])
            ->sum(DB::raw('coalesce(delivery_note_items.quantity_required, 0) - coalesce(delivery_note_items.quantity_picked, 0)'));
    }

    public function handle(bool $apply = false): array
    {
        $tradeUnit = $this->getTradeUnit();

        if (!$tradeUnit) {
            return ['org_stocks' => collect(), 'blocked' => false, 'remaining_bom_lines' => 0, 'unpicked_labels' => 0.0, 'written_off' => 0, 'discontinued' => 0];
        }

        $remainingBomLines = $this->countRemainingBomLines($tradeUnit);
        $unpickedLabels    = $this->countUnpickedLabels($tradeUnit);
        $orgStocks         = $this->getOrgStocks($tradeUnit);

        if ($apply && ($remainingBomLines > 0 || $unpickedLabels > 0)) {
            return [
                'org_stocks'          => $orgStocks,
                'blocked'             => true,
                'remaining_bom_lines' => $remainingBomLines,
                'unpicked_labels'     => $unpickedLabels,
                'written_off'         => 0,
                'discontinued'        => 0,
            ];
        }

        $writtenOff   = 0;
        $discontinued = 0;

        foreach ($orgStocks as $orgStock) {
            foreach ($orgStock->locationOrgStocks as $locationOrgStock) {
                if ($locationOrgStock->quantity == 0) {
                    continue;
                }

                if ($apply) {
                    AuditLocationOrgStock::make()->action($locationOrgStock, [
                        'quantity' => 0,
                        'reason'   => OrgStockMovementReasonEnum::DATA_FIX->value,
                        'note'     => 'IAL01 import address labels were never real stock, retired with the picking hack',
                    ]);
                }

                $writtenOff++;
            }

            if ($orgStock->state != OrgStockStateEnum::DISCONTINUED) {
                if ($apply) {
                    UpdateOrgStock::make()->action($orgStock, [
                        'state'                           => OrgStockStateEnum::DISCONTINUED,
                        'discontinued_in_organisation_at' => now(),
                    ], strict: false);
                }

                $discontinued++;
            }
        }

        return [
            'org_stocks'          => $orgStocks,
            'blocked'             => false,
            'remaining_bom_lines' => $remainingBomLines,
            'unpicked_labels'     => $unpickedLabels,
            'written_off'         => $writtenOff,
            'discontinued'        => $discontinued,
        ];
    }

    public function asCommand(Command $command): int
    {
        $tradeUnit = $this->getTradeUnit();

        if (!$tradeUnit) {
            $command->error('Trade unit '.self::CONSUMABLE_CODE.' not found, nothing to do.');

            return Command::FAILURE;
        }

        $apply  = (bool) $command->option('apply');
        $result = $this->handle($apply);

        $command->table(
            ['Org', 'SKO', 'State', 'Qty in locations', 'Locations holding stock'],
            $result['org_stocks']->map(fn (OrgStock $orgStock) => [
                $orgStock->organisation->code,
                $orgStock->id,
                $orgStock->state->value,
                $orgStock->quantity_in_locations,
                $orgStock->locationOrgStocks->where('quantity', '<>', 0)->count(),
            ])
        );

        if ($result['blocked']) {
            if ($result['remaining_bom_lines'] > 0) {
                $command->error($result['remaining_bom_lines'].' bill of materials line(s) still reference '.self::CONSUMABLE_CODE.'.');
                $command->warn('Run catalogue:remove-ial01-from-boms --apply first.');
            }

            if ($result['unpicked_labels'] > 0) {
                $command->error($result['unpicked_labels'].' label(s) are still waiting to be picked on delivery notes in the warehouse.');
                $command->warn('Let those delivery notes dispatch first, a picker cannot pick from an empty location.');
            }

            return Command::FAILURE;
        }

        $command->line('Labels still to pick on undispatched delivery notes: '.$result['unpicked_labels']);

        $command->info(($apply ? 'Wrote off' : 'Would write off').' '.$result['written_off']
            .' location(s) and '.($apply ? 'discontinued' : 'would discontinue').' '.$result['discontinued'].' SKO(s).');

        if (!$apply) {
            $command->warn('Dry run, nothing was changed. Re-run with --apply to retire.');
        }

        return Command::SUCCESS;
    }
}
