<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Wed, 19 Oct 2022 18:36:42 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\EmailBulkRun;

use App\Actions\Comms\EmailBulkRun\Hydrators\EmailBulkRunHydrateCumulativeDispatchedEmails;
use App\Actions\Comms\EmailBulkRun\Hydrators\EmailBulkRunHydrateDispatchedEmails;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Enums\Comms\DispatchedEmail\DispatchedEmailStateEnum;
use App\Models\Comms\EmailBulkRun;

class HydrateEmailBulkRuns
{
    use WithHydrateCommand;

    public string $commandSignature = 'hydrate:email_bulk_runs {organisations?*} {--s|slugs=}';

    public function __construct()
    {
        $this->model = EmailBulkRun::class;
    }

    public function handle(EmailBulkRun $emailBulkRun): void
    {
        EmailBulkRunHydrateCumulativeDispatchedEmails::run($emailBulkRun, DispatchedEmailStateEnum::SENT);
        EmailBulkRunHydrateDispatchedEmails::run($emailBulkRun);
    }

}
