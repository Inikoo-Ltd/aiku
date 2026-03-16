<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:09:35 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Mailshot;

use App\Actions\Comms\Traits\WithProcessEmailStyles;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Comms\EmailBulkRun;
use App\Models\Comms\Mailshot;

class GetHtmlLayout extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithProcessEmailStyles;


    public function handle(Mailshot|EmailBulkRun $parent): string
    {
        if ($parent instanceof EmailBulkRun) {
            $compiledLayout = $parent->outbox->emailOngoingRun->email->liveSnapshot->compiled_layout;
        } else {
            $compiledLayout = $parent->email->liveSnapshot->compiled_layout;
        }
        return $compiledLayout;
    }
}
