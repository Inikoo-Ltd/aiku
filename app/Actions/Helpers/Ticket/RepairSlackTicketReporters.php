<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 12 Sep 2026 22:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Ticket;

use App\Actions\Helpers\Ticket\Concerns\WithSlack;
use App\Models\Helpers\Ticket;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Tickets raised from Slack before their reporter could be matched: map the Slack user with
 * `users.slack_user_id` (or fix their email/username) and run `tickets:repair_slack_reporters`.
 */
class RepairSlackTicketReporters
{
    use AsAction;
    use WithSlack;

    public string $commandSignature = 'tickets:repair_slack_reporters';

    public function handle(): int
    {
        $repaired = 0;
        Ticket::whereNull('reporter_id')->whereNotNull('data->slack->user_id')->each(function (Ticket $ticket) use (&$repaired) {
            if ($user = $this->slackUserToAikuUser(data_get($ticket->data, 'slack.user_id'))) {
                $ticket->update(['reporter_type' => 'User', 'reporter_id' => $user->id]);
                $repaired++;
            }
        });

        return $repaired;
    }

    public function asCommand(Command $command): int
    {
        $command->info($this->handle().' tickets repaired');

        return 0;
    }
}
