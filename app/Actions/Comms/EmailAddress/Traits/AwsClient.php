<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:09:35 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\EmailAddress\Traits;

use App\Actions\Comms\EmailAddress\ProcessGetSesClient;
use Aws\Ses\SesClient;

trait AwsClient
{
    public function getSesClient(?int $outboxId = null): SesClient
    {
        $candidates = ProcessGetSesClient::run($outboxId);

        return $this->buildSesClient($candidates[0]);
    }

    public function buildSesClient(array $credentials): SesClient
    {
        return new SesClient([
            'version'     => 'latest',
            'region'      => $credentials['region'],
            'credentials' => [
                'key'    => $credentials['key'],
                'secret' => $credentials['secret'],
            ],
        ]);
    }
}
