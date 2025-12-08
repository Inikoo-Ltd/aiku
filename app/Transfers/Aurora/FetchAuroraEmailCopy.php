<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 13 Nov 2024 21:15:28 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use Illuminate\Support\Facades\DB;

class FetchAuroraEmailCopy extends FetchAurora
{
    protected function parseModel(): void
    {
        $body = gzuncompress($this->auroraModelData->{'Email Tracking Email Copy Compressed Body'});
        if ($body == '') {
            return;
        }
        $dispatchedEmail = $this->parseDispatchedEmail($this->organisation->id.':'.$this->auroraModelData->{'Email Tracking Email Copy Key'});

        if (! $dispatchedEmail) {
            return;
        }

        $this->parsedData['dispatchedEmail'] = $dispatchedEmail;

        $this->parsedData['emailCopy'] = [
            'subject' => $this->auroraModelData->{'Email Tracking Email Copy Subject'},
            'body' => $body,
            'fetched_at' => now(),
            'last_fetched_at' => now(),
            'source_id' => $this->organisation->id.':'.$this->auroraModelData->{'Email Tracking Email Copy Key'},
        ];
    }

    protected function fetchData($id): ?object
    {
        return DB::connection('aurora')
            ->table('Email Tracking Email Copy')
            ->where('Email Tracking Email Copy Key', $id)->first();
    }
}
