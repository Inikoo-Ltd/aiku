<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:09:35 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Mailshot;

use App\Actions\Comms\Mailshot\Hydrators\MailshotHydrateDispatchedEmails;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Comms\Mailshot;

class HydrateMailshots
{
    use WithHydrateCommand;

    public string $commandSignature = 'hydrate:mailshots {organisations?*} {--slugs}';

    public function __construct()
    {
        $this->model = Mailshot::class;
    }

    public function handle(Mailshot $mailshot): void
    {
        MailshotHydrateDispatchedEmails::run($mailshot);
    }


}
