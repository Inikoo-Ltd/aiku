<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 20 Nov 2026 16:15:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack;

use App\Actions\Helpers\Ticket\Concerns\WithSlack;
use App\Models\Chat\SlackMessage;
use App\Models\Chat\SlackUser;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

class SyncSlackUsers
{
    use AsAction;
    use WithSlack;

    public string $commandSignature = 'slack:sync-users';

    public string $commandDescription = 'Keep the people of the Slack workspace in aiku, matched to aiku users by email, so Slack messages show their author';

    /**
     * @return array{people: int, matched: int, messages_without_aiku_user: int}
     */
    public function handle(): array
    {
        $client = $this->slackClient() ?? throw new RuntimeException('Slack bot token is not set');
        $people = 0;
        $cursor = null;

        do {
            $page = $this->slackCall($client, 'users.list', array_filter(['limit' => 200, 'cursor' => $cursor]));
            foreach (Arr::get($page, 'members', []) as $member) {
                StoreSlackUser::run($member);
                $people++;
            }
            $cursor = Arr::get($page, 'response_metadata.next_cursor');
        } while ($cursor);

        return [
            'people'          => $people,
            'matched'         => SlackUser::whereNotNull('user_id')->count(),
            'messages_without_aiku_user' => SlackMessage::whereNull('user_id')->whereNotNull('slack_user_id')->count(),
        ];
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $totals = $this->handle();
        $command->info("Slack people: {$totals['people']}, matched to aiku users: {$totals['matched']}, messages by people not matched to an aiku user: {$totals['messages_without_aiku_user']}");

        return 0;
    }
}
