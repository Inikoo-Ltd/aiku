<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 02 Sep 2026 14:20:00 Central European Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage;

use App\Enums\Helpers\Audit\AuditEventEnum;
use App\Models\Helpers\Snapshot;
use App\Models\Web\Webpage;
use Illuminate\Support\Facades\Event;
use Lorisleiva\Actions\Concerns\AsAction;
use OwenIt\Auditing\Events\AuditCustom;

class RecordWebpagePublishHistory
{
    use AsAction;

    public function handle(Webpage $webpage, Snapshot $snapshot): void
    {
        $webpage->auditEvent     = AuditEventEnum::PUBLISHED->value;
        $webpage->isCustomEvent  = true;
        $webpage->auditCustomOld = [];
        $webpage->auditCustomNew = ['content' => $snapshot->comment ?: __('New version published')];

        Event::dispatch(new AuditCustom($webpage));
    }
}
