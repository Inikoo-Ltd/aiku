<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026 20:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Enums\Production\JobOrder\JobOrderStateEnum;
use Illuminate\Support\Facades\DB;

class FetchAuroraJobOrder extends FetchAurora
{
    protected function parseModel(): void
    {
        $production = $this->organisation->productions()->first();
        if (!$production) {
            print "Error No production found in organisation ".$this->organisation->slug." for job order ".$this->auroraModelData->{'Purchase Order Key'}."\n";

            return;
        }

        $this->parsedData['production'] = $production;

        $createdAt   = $this->parseDatetime($this->auroraModelData->{'Purchase Order Creation Date'});
        $submittedAt = $this->parseDatetime($this->auroraModelData->{'Purchase Order Submitted Date'});
        $confirmedAt = $this->parseDatetime($this->auroraModelData->{'Purchase Order Confirmed Date'});
        $receivedAt  = $this->parseDatetime($this->auroraModelData->{'Purchase Order Received Date'});
        $cancelledAt = $this->parseDatetime($this->auroraModelData->{'Purchase Order Cancelled Date'});

        // ponytail: JobOrderStateEnum has no cancelled case, Aurora cancelled job orders land as not_received
        $state = match ($this->auroraModelData->{'Purchase Order State'}) {
            'InProcess' => JobOrderStateEnum::IN_PROCESS,
            'Submitted' => JobOrderStateEnum::SUBMITTED,
            'Confirmed' => JobOrderStateEnum::CONFIRMED,
            'Cancelled' => JobOrderStateEnum::NOT_RECEIVED,
            default => JobOrderStateEnum::RECEIVED,
        };

        $this->parsedData['job_order'] = [
            'reference'       => (string)($this->auroraModelData->{'Purchase Order Public ID'} ?: $this->auroraModelData->{'Purchase Order Key'}),
            'state'           => $state,
            'date'            => $this->auroraModelData->{'Purchase Order Date'},
            'in_process_at'   => $createdAt,
            'submitted_at'    => $submittedAt,
            'confirmed_at'    => $confirmedAt,
            'received_at'     => $receivedAt,
            'not_received_at' => $cancelledAt,
            'created_at'      => $createdAt,
            'source_id'       => $this->organisation->id.':'.$this->auroraModelData->{'Purchase Order Key'},
        ];
    }

    protected function fetchData($id): object|null
    {
        return DB::connection('aurora')
            ->table('Purchase Order Dimension')
            ->where('Purchase Order Type', 'Production')
            ->where('Purchase Order Key', $id)
            ->first();
    }
}
