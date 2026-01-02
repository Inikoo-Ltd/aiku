<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Wed, 19 Oct 2022 18:36:42 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\DispatchedEmail;

use App\Actions\Comms\DispatchedEmail\Hydrators\DispatchedEmailHydrateClicks;
use App\Actions\Comms\DispatchedEmail\Hydrators\DispatchedEmailHydrateEmailTracking;
use App\Actions\Comms\DispatchedEmail\Hydrators\DispatchedEmailHydrateReads;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Comms\DispatchedEmail;

class HydrateDispatchedEmails
{
    use WithHydrateCommand;

    public string $commandSignature = 'hydrate:dispatched_emails {organisations?*} {--s|slugs=}';

    public function __construct()
    {
        $this->model = DispatchedEmail::class;
    }

    public function handle(DispatchedEmail $dispatchedEmail): void
    {
        DispatchedEmailHydrateClicks::run($dispatchedEmail);
        DispatchedEmailHydrateReads::run($dispatchedEmail);
        DispatchedEmailHydrateEmailTracking::run($dispatchedEmail);
    }

}
