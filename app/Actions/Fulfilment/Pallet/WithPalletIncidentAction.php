<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Apr 2025 00:55:31 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\Pallet;

use App\Actions\Fulfilment\Pallet\Search\PalletRecordSearch;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Models\Fulfilment\Pallet;
use Illuminate\Support\Arr;

trait WithPalletIncidentAction
{
    use WithActionUpdate;
    public function processIncident(Pallet $pallet, PalletStateEnum $incidentState, $modelData): Pallet
    {
        $reporterId = Arr::pull($modelData, 'reporter_id');
        data_set($modelData, 'state', $incidentState);
        data_set($modelData, 'status', PalletStatusEnum::INCIDENT);
        data_set($modelData, 'set_as_incident_at', now());

        data_set($modelData, 'incident_report', [
            'type'        => $incidentState->value,
            'message'     => Arr::get($modelData, 'message'),
            'reporter_id' => $reporterId,
            'date'        => now()
        ]);

        $pallet = UpdatePallet::run($pallet, Arr::except($modelData, 'message'));
        PalletRecordSearch::dispatch($pallet);
        return $pallet;
    }
}
