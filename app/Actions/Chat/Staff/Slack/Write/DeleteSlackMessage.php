<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 21:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack\Write;

use App\Actions\Chat\Staff\Slack\StoreSlackMessage;
use App\Models\Chat\SlackChannel;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Deletes the person's own message in Slack with their own token, then soft deletes it as a message_deleted event
 * would; replies to a deleted thread parent stay. A message already gone in Slack is removed here too.
 */
class DeleteSlackMessage
{
    use AsAction;
    use WithSlackMessageWrite;

    public function rules(): array
    {
        return [
            'ts' => ['required', 'string', 'regex:/^\d{1,12}\.\d{1,8}$/'],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function handle(User $user, SlackChannel $slackChannel, string $ts): ?array
    {
        $this->ensureMayWrite($user, $slackChannel);
        $message = $this->liveMessage($slackChannel, $ts);
        $client  = $this->clientFor($user, standInAllowed: true, channel: $slackChannel);
        $this->ensureOwnMessage($message, $client);

        $this->callSlack($client, 'chat.delete', ['channel' => $slackChannel->slack_id, 'ts' => $message->ts], ['message_not_found']);

        StoreSlackMessage::make()->handle([
            'channel'      => $slackChannel->slack_id,
            'channel_type' => 'channel',
            'subtype'      => 'message_deleted',
            'deleted_ts'   => $message->ts,
        ]);

        return $this->row($slackChannel, $message->ts, $user);
    }

    public function asController(SlackChannel $slackChannel, ActionRequest $request): array
    {
        return ['message' => $this->handle($request->user(), $slackChannel, $request->validated('ts'))];
    }
}
