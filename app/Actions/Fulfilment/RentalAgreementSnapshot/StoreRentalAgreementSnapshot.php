<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Apr 2024 08:53:02 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\RentalAgreementSnapshot;

use App\Actions\Fulfilment\RentalAgreement\Hydrators\RentalAgreementHydrateSnapShots;
use App\Actions\OrgAction;
use App\Models\Fulfilment\RentalAgreement;
use App\Models\Fulfilment\RentalAgreementSnapshot;
use Illuminate\Support\Arr;

class StoreRentalAgreementSnapshot extends OrgAction
{
    public function handle(RentalAgreement $rentalAgreement, bool $firstSnapshot, ?array $updateData = null): RentalAgreementSnapshot
    {

        $modelData = [
            'data' => [
                'reference'     => $rentalAgreement->reference,
                'billing_cycle' => $rentalAgreement->billing_cycle,
                'pallets_limit' => $rentalAgreement->pallets_limit,
            ],
            'is_first_snapshot' => $firstSnapshot
        ];
        if ($firstSnapshot) {
            $modelData['data']['clauses_added'] = $rentalAgreement->clauses()->count();
        } else {
            $modelData['data']['clauses_added']   = Arr::get($updateData, 'clauses_added', 0);
            $modelData['data']['clauses_removed'] = Arr::get($updateData, 'clauses_removed', 0);
            $modelData['data']['clauses_updated'] = Arr::get($updateData, 'clauses_updated', 0);

        }

        /** @var RentalAgreementSnapshot $rentalAgreementSnapshot */
        $rentalAgreementSnapshot = $rentalAgreement->snapshots()->create($modelData);
        $rentalAgreement->updateQuietly(['current_snapshot_id' => $rentalAgreementSnapshot->id]);

        RentalAgreementHydrateSnapShots::dispatch($rentalAgreement);

        return $rentalAgreementSnapshot;
    }


}
