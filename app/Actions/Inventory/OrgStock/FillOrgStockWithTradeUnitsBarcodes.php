<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 31 Jul 2026 12:00:00 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock;

use App\Models\Goods\TradeUnit;
use App\Models\Inventory\OrgStock;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Best guess for the barcode a packer will scan off the shelf: when the org stock is one single
 * trade unit, the box in the warehouse is that trade unit, so it carries the trade unit barcode.
 *
 * Org stocks made of several trade units, or of several copies of the same trade unit, are skipped,
 * their outer packaging carries a barcode of its own that nothing in the system knows about, and
 * guessing there would send a packer the wrong goods. So are the ones given a barcode by hand.
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

        if ($tradeUnit->pivot->quantity != 1) {
            return null;
        }

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
     */
    public function isAmbiguous(OrgStock $orgStock, TradeUnit $tradeUnit, string $barcode): bool
    {
        return OrgStock::where('organisation_id', $orgStock->organisation_id)
            ->where('id', '!=', $orgStock->id)
            ->where(function ($query) use ($tradeUnit, $barcode) {
                $query->where('barcode', $barcode)
                    ->orWhereHas('tradeUnits', fn ($query) => $query->where('trade_units.id', $tradeUnit->id));
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
