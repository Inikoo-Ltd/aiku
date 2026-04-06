<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Monday, 6 Apr 2026 10:32:10 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\DispatchedEmail\Hydrators;

use App\Actions\CRM\Prospect\UpdateProspectEmailOpened;
use App\Actions\CRM\Prospect\UpdateProspectEmailSent;
use App\Enums\Comms\DispatchedEmail\DispatchedEmailStateEnum;
use App\Models\Comms\DispatchedEmail;
use App\Models\CRM\Prospect;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Illuminate\Console\Command;

class DispatchedEmailHydrateProspect implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'low-priority';

    public function getJobUniqueId(int $dispatchedEmailId): string
    {
        return $dispatchedEmailId;
    }

    public function handle(int $dispatchedEmailId): void
    {
        $dispatchedEmail = DispatchedEmail::find($dispatchedEmailId);
        if (!$dispatchedEmail) {
            return;
        }

        $prospectData = DB::table('prospect_has_dispatched_emails')
            ->where('dispatched_email_id', $dispatchedEmailId)
            ->first();

        if (!$prospectData) {
            return;
        }

        $prospect = Prospect::find($prospectData->prospect_id);
        if (!$prospect) {
            return;
        }

        $state = $dispatchedEmail->state;

        // TODO: Update this conditions
        if ($state == DispatchedEmailStateEnum::DELIVERED || $state == DispatchedEmailStateEnum::SENT) {
            UpdateProspectEmailSent::make()->action($prospect);
        } elseif ($state == DispatchedEmailStateEnum::OPENED) {
            UpdateProspectEmailOpened::make()->action($prospect, now());
        }
    }

    public string $commandSignature = 'dispatched-email:hydrate-prospect {dispatchedID}';

    public function asCommand(Command $command): int
    {
        $dispatchedID = $command->argument('dispatchedID');
        $this->handle($dispatchedID);

        return 0;
    }
}
