<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 23:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack\Write;

use App\Models\Chat\SlackChannel;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The text of the person's own message for the edit box, when its row arrived live (broadcast rows carry no text).
 * Only the author's own row ever has it.
 */
class GetOwnSlackMessageText
{
    use AsAction;
    use WithSlackMessageWrite;

    protected function writesDirect(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ts' => ['required', 'string', 'regex:/^\d{1,12}\.\d{1,8}$/'],
        ];
    }

    public function handle(User $user, SlackChannel $slackChannel, string $ts): string
    {
        $this->ensureMayWrite($user, $slackChannel);
        $row = $this->row($slackChannel, $this->liveMessage($slackChannel, $ts)->ts, $user);
        if (!isset($row['text'])) {
            $this->refuse(__('You can only change your own messages'));
        }

        return $row['text'];
    }

    public function asController(SlackChannel $slackChannel, ActionRequest $request): array
    {
        return ['text' => $this->handle($request->user(), $slackChannel, $request->validated('ts'))];
    }
}
