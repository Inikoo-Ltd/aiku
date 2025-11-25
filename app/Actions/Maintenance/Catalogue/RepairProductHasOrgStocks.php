<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 24 Nov 2025 22:38:17 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Catalogue;

use App\Models\Inventory\OrgStock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairProductHasOrgStocks
{
    use AsAction;


    public function getCommandSignature(): string
    {
        return 'maintenance:repair_product_has_org_stocks ';
    }

    public function asCommand(Command $command): int
    {
        $productHasOrgStocks = DB::table('product_has_org_stocks')->whereNull('source_id')->get();
        foreach ($productHasOrgStocks as $productHasOrgStock) {
            $orgStock = OrgStock::find($productHasOrgStock->org_stock_id);

            $packedIn = null;

            $tradeUnitsCount = $orgStock->tradeUnits->count();
            if ($tradeUnitsCount == 1) {
                $packedIn = $orgStock->tradeUnits->first()->pivot->quantity;
                // If packedIn is not an integer, set it to null
                if ($packedIn !== null) {
                    $pf = (float)$packedIn;
                    if (floor($pf) != $pf) {
                        $packedIn = null;
                    }
                }

                // Ensure $packedIn is an integer type when present
                if ($packedIn !== null) {
                    $packedIn = (int)$packedIn;
                }
            }


            list($smallestDividend, $correspondingDivisor) = findSmallestFactors($productHasOrgStock->quantity);


            DB::table('product_has_org_stocks')
                ->where('product_id', $productHasOrgStock->product_id)
                ->where('org_stock_id', $productHasOrgStock->org_stock_id)
                ->update([
                    'trade_units_per_org_stock' => $packedIn,
                    'divisor'                   => $correspondingDivisor,
                    'dividend'                  => $smallestDividend
                ]);


            // Print only when quantity is not an integer
            $q = $productHasOrgStock->quantity;
            if ($q !== null) {
                $qf = (float)$q;
                if (floor($qf) != $qf) {
                    print "non-integer qty → org_stock_id:$productHasOrgStock->org_stock_id packed_in:$packedIn qty:$q smallest_dividend:$smallestDividend divisor:$correspondingDivisor\n";
                }
            }
        }

        return 0;
    }
}
