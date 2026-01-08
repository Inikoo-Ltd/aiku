<?php

/*
 * Author: Eka Yudinata <ekayudinata@gmail.com>
 * Created: Thu, 19 Dec 2024 18:08:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Eka Yudinata
 */

namespace App\Actions\Maintenance\Comms;

use App\Enums\Comms\Outbox\OutboxStateEnum;
use App\Enums\Comms\Outbox\OutboxSubTypeEnum;
use App\Models\Comms\Outbox;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairOutboxStateFromReorderReminderSettings
{
    use AsAction;
    public string $commandSignature = 'repair:in-active-outbox-reorder-reminder';


    public function handle(): void
    {
        Outbox::query()->where('sub_type', OutboxSubTypeEnum::REORDER_REMINDER)
               ->whereNull('days_after')
               ->update([
                   'state' => OutboxStateEnum::IN_PROCESS,
               ]);

    }

    public function asCommand(): void
    {
        $this->run();
    }
}
