<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 15 Feb 2025 16:32:33 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora\Api;

use App\Actions\OrgAction;
use App\Actions\Transfers\Aurora\FetchAuroraDispatchedEmails;

/**
 * @property string $fetcher
 */
class ProcessAuroraDispatchedEmail extends OrgAction
{
    use WithProcessAurora;

    public string $jobQueue = 'low-priority';

    public function __construct()
    {
        $this->fetcher = FetchAuroraDispatchedEmails::class;
    }
}
