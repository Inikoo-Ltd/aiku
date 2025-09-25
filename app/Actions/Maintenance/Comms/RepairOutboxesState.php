<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 20 Sept 2025 20:33:01 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Comms;

use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithOutboxBuilder;
use App\Enums\Comms\Email\EmailBuilderEnum;
use App\Enums\Comms\EmailTemplate\EmailTemplateStateEnum;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\Comms\Outbox\OutboxStateEnum;
use App\Models\Comms\EmailTemplate;
use App\Models\Comms\Outbox;
use Illuminate\Console\Command;

class RepairOutboxesState
{
    use WithActionUpdate;
    use WithOutboxBuilder;

    /**
     * @throws \Throwable
     */
    protected function handle(Outbox $outbox): void
    {
        if ($outbox->emailOngoingRun) {
            $email = $outbox->emailOngoingRun->email;

            if (!$email) {

                $emailTemplate = EmailTemplate::where('state', EmailTemplateStateEnum::ACTIVE)
                    ->whereJsonContains('data->outboxes', $outbox->code)->first();
                $model = null;
                if ($outbox->code == OutboxCodeEnum::DELIVERY_CONFIRMATION) {
                    $model = $outbox->shop;
                }

                if ($model && $emailTemplate) {
                    $this->createEmail($model, $outbox->code, $outbox->emailOngoingRun, $emailTemplate, $outbox);
                    $outbox->refresh();
                    $email = $outbox->emailOngoingRun->email;
                } else {
                    dd($outbox->code, $outbox->shop->name, $emailTemplate);
                }


            }

            if ($email->liveSnapshot
                && $email->builder != EmailBuilderEnum::BLADE
                && $email->liveSnapshot->compiled_layout == null) {
                $snapshot = $email->liveSnapshot;

                $outbox->update([
                    'state' => OutboxStateEnum::IN_PROCESS
                ]);
                $email->update([
                    'live_snapshot_id' => null,
                ]);
                $snapshot->delete();
            }
        }
    }

    public string $commandSignature = 'repair:outboxes_state';

    public function asCommand(Command $command): void
    {
        $count = Outbox::count();

        $command->info("outboxes: $count");

        Outbox::orderBy('id')
            ->chunk(
                1000,
                function ($outboxes) {
                    foreach ($outboxes as $outbox) {
                        $this->handle($outbox);
                    }
                }
            );
    }

}
