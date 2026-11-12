<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\WhatsappBulkRun;

use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\Comms\Outbox\OutboxStateEnum;
use App\Models\Comms\WhatsappOutbox;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The scheduled start of a bulk WhatsApp outbox. It looks only at the WhatsApp outboxes, so it
 * runs whether the email outbox of the same code is active or not.
 */
class RunWhatsappBulkRuns
{
    use AsAction;

    public string $jobQueue = 'whatsapp-send';

    public function handle(OutboxCodeEnum $outboxCode): void
    {
        WhatsappOutbox::where('code', $outboxCode->value)
            ->where('state', OutboxStateEnum::ACTIVE->value)
            ->whereNotNull('meta_message_template_id')
            ->pluck('id')
            ->each(fn (int $whatsappOutboxId) => PrepareWhatsappBulkRun::dispatch($whatsappOutboxId));
    }

    public string $commandSignature = 'run:whatsapp-bulk-runs {code : oos_in_order_notification or oos_notification}';

    public function asCommand(Command $command): int
    {
        $outboxCode = OutboxCodeEnum::tryFrom((string) $command->argument('code'));

        if (!in_array($outboxCode, [OutboxCodeEnum::OOS_IN_ORDER_NOTIFICATION, OutboxCodeEnum::OOS_NOTIFICATION], true)) {
            $command->error('Unknown bulk WhatsApp outbox code.');

            return 1;
        }

        $this->handle($outboxCode);

        return 0;
    }
}
