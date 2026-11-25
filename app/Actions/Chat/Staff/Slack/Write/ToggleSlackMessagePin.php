<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Nov 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack\Write;

use App\Actions\Chat\Staff\Slack\StoreSlackPin;
use App\Events\SlackChannelMessageChanged;
use App\Models\Chat\SlackChannel;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Pins or unpins a kept message in Slack with the person's own token, then marks it at once through the code the
 * pin events use, so the pin_added / pin_removed event Slack sends back changes nothing.
 */
class ToggleSlackMessagePin
{
    use AsAction;
    use WithSlackMessageWrite;

    public function rules(): array
    {
        return [
            'ts'     => ['required', 'string', 'regex:/^\d{1,12}\.\d{1,8}$/'],
            'pinned' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array{message: array<string, mixed>|null, pinned_count: int}
     */
    public function handle(User $user, SlackChannel $slackChannel, string $ts, bool $pinned): array
    {
        $this->ensureMayWrite($user, $slackChannel);
        $message = $this->liveMessage($slackChannel, $ts);
        $client  = $this->clientFor($user);

        $this->callSlack(
            $client,
            $pinned ? 'pins.add' : 'pins.remove',
            ['channel' => $slackChannel->slack_id, 'timestamp' => $message->ts],
            [$pinned ? 'already_pinned' : 'no_pin']
        );

        StoreSlackPin::make()->handle($slackChannel, ['type' => 'message', 'message' => ['ts' => $message->ts], 'created' => time(), 'created_by' => $client->slackUserId()], $pinned);
        rescue(fn () => SlackChannelMessageChanged::dispatch($slackChannel, $message->refresh(), 'pin'));

        return [
            'message'      => $this->row($slackChannel, $message->ts, $user),
            'pinned_count' => $slackChannel->messages()->whereNotNull('pinned_at')->count(),
        ];
    }

    public function asController(SlackChannel $slackChannel, ActionRequest $request): array
    {
        return $this->handle($request->user(), $slackChannel, $request->validated('ts'), (bool) $request->validated('pinned'));
    }
}
