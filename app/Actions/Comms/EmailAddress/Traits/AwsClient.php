<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:09:35 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\EmailAddress\Traits;

use Aws\Ses\SesClient;

trait AwsClient
{
    public function getSesClient(): SesClient
    {
        //todo, do this properly, this is a temporal fix;
        $region = rand(0, 1) === 1 ? 'us-east-1' : config('services.ses.region');
        $region = 'us-east-1';
        return new SesClient([
            'version'     => 'latest',
            'region'      => $region,
            'credentials' => [
                'key'    => config('services.ses.key'),
                'secret' => config('services.ses.secret'),
            ],
        ]);
    }
}
