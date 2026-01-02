<?php

/*
 * Author: Eka Yudinata <ekayudinata@gmail.com>
 * Created: Thu, 19 Dec 2024 18:08:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Eka Yudinata
 */

namespace App\Actions\Maintenance\Comms;

use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\Comms\Outbox\OutboxSubTypeEnum;
use App\Models\Comms\Outbox;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairOutboxSubTypeFromReorderReminderSettings
{
    use AsAction;

    public string $commandSignature = 'repair:outbox-sub-type-reorder-reminder';


    public function handle(): void
    {
        Outbox::query()->whereIn('code', [
            OutboxCodeEnum::REORDER_REMINDER,
            OutboxCodeEnum::REORDER_REMINDER_2ND,
            OutboxCodeEnum::REORDER_REMINDER_3RD
        ])->update([
            'sub_type' => OutboxSubTypeEnum::REORDER_REMINDER,
        ]);
    }

    public function asCommand(): void
    {
        $this->run();
    }
}
