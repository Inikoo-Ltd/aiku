<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 31 Jul 2026 12:00:00 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock;

use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Models\Goods\TradeUnit;
use App\Models\Inventory\OrgStock;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Best guess for the barcode a packer will scan off the shelf: when the org stock is one single
 * trade unit, the goods in the warehouse are that trade unit, so they carry its barcode.
 *
 * Org stocks holding several copies of the same trade unit get it too, the packer scans one of the
 * units and that stands for the whole org stock, which is a thing packers have to be trained on.
 *
 * Org stocks made of several different trade units are skipped, a scan of any one of them says
 * nothing about the rest. So are the ones given a barcode by hand.
 */
class FillOrgStockWithTradeUnitsBarcodes
{
    use AsAction;

    public string $commandSignature = 'org_stocks:fill_barcodes
        {--apply : Write the barcodes, without this the command only reports how many it would fill}';

    public function handle(OrgStock $orgStock): ?string
    {
        $barcode = $this->guess($orgStock);

        if ($barcode) {
            $orgStock->update(['barcode' => $barcode]);
        }

        return $barcode;
    }

    public function guess(OrgStock $orgStock): ?string
    {
        if ($orgStock->independent_barcode) {
            return null;
        }

        $tradeUnits = $orgStock->tradeUnits;

        if ($tradeUnits->count() != 1) {
            return null;
        }

        $tradeUnit = $tradeUnits->first();
        $barcode = $tradeUnit->getAttributeValue('barcode');

        if (blank($barcode) || $barcode == $orgStock->barcode) {
            return null;
        }

        return $this->isAmbiguous($orgStock, $tradeUnit, $barcode) ? null : $barcode;
    }

    /**
     * A scan has to name one org stock, so the guess is only worth writing when nothing else in the
     * organisation answers to the same barcode, neither an org stock already carrying it nor a
     * sibling org stock built from the same trade unit, which would be filled with it in turn.
     *
     * Discontinued siblings are no competition, nobody picks them, so the live org stock keeps the
     * barcode and the discontinued one is the side that steps aside.
     */
    public function isAmbiguous(OrgStock $orgStock, TradeUnit $tradeUnit, string $barcode): bool
    {
        return OrgStock::where('organisation_id', $orgStock->organisation_id)
            ->where('id', '!=', $orgStock->id)
            ->where(function ($query) use ($tradeUnit, $barcode) {
                $query->where('barcode', $barcode)
                    ->orWhere(fn ($query) => $query->where('state', '!=', OrgStockStateEnum::DISCONTINUED)
                        ->whereHas('tradeUnits', fn ($query) => $query->where('trade_units.id', $tradeUnit->id)));
            })
            ->exists();
    }

    public function asCommand(Command $command): int
    {
        $apply  = $command->option('apply');
        $filled = 0;

        OrgStock::where('is_single_trade_unit', true)
            ->where('independent_barcode', false)
            ->whereNull('barcode')
            ->with('tradeUnits')
            ->chunkById(1000, function ($orgStocks) use ($apply, &$filled) {
                foreach ($orgStocks as $orgStock) {
                    $filled += ($apply ? $this->handle($orgStock) : $this->guess($orgStock)) ? 1 : 0;
                }
            });

        $command->info($apply ? "$filled org stocks filled" : "$filled org stocks would be filled, run with --apply");

        return 0;
    }
}
