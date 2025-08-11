<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Jul 2025 15:47:37 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock\Hydrators;

use App\Models\Inventory\OrgStock;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class OrgStockHydratePackedIn implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(OrgStock $orgStock): string
    {
        return $orgStock->id;
    }


    public function handle(OrgStock $orgStock): void
    {
        $packedIn = null;

        $tradeUnits = $orgStock->tradeUnits;
        if ($tradeUnits->count() == 1) {
            $packedIn = $tradeUnits->first()->pivot->quantity;
            if (floor($packedIn) != $packedIn) {
                $packedIn = null;
            }
            $packedIn = intval($packedIn);

            if ($packedIn > 50000 || $packedIn <= 0) {
                $packedIn = null;
            }
        }

        $orgStock->update(
            [
                'packed_in' => $packedIn
            ]
        );
    }


}
