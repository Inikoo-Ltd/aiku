<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Nov 2026 15:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Chat;

use App\Actions\Chat\Staff\Slack\Write\SendSlackMessage;
use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackUser;
use App\Models\Chat\StaffMessageSchedule;
use App\Models\SysAdmin\User;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A scheduled message as its author sees it in the Scheduled list; only ever sent to the person who wrote it.
 *
 * @mixin StaffMessageSchedule
 */
class StaffMessageScheduleResource extends JsonResource
{
    public function toArray($request): array
    {
        $timezone = $this->user->timezone_name;

        return [
            'id'             => $this->id,
            'state'          => $this->state,
            'text'           => $this->readableText(),
            'has_mentions'   => (bool) preg_match(SendSlackMessage::MENTION, $this->text),
            'is_reply'       => $this->thread_ts !== null || $this->parent_id !== null,
            'send_at'        => $this->send_at->toIso8601String(),
            'send_at_local'  => $this->send_at->copy()->setTimezone($timezone)->format('Y-m-d H:i'),
            'timezone'       => $timezone,
            'failure_reason' => $this->failure_reason,
            'target'         => $this->slack_channel_id ? [
                'kind'       => 'slack',
                'id'         => $this->slack_channel_id,
                'name'       => $this->slackChannel->direct_type ? __('Slack direct message') : '#'.$this->slackChannel->name,
                'url'        => route('grp.chat.staff.slack.show', ['slackChannel' => $this->slack_channel_id]),
            ] : [
                'kind'       => 'verba',
                'id'         => $this->staff_conversation_id,
                'name'       => $this->staffConversation->name ?: $this->staffConversation->participants
                    ->reject(fn (User $user) => $user->id === $this->user_id)
                    ->map(fn (User $user) => $user->chatName())
                    ->join(', '),
                'url'        => route($this->staffConversation->isVerbration() ? 'grp.chat.staff.groups.show' : 'grp.chat.staff.show', $this->staffConversation->ulid),
            ],
        ];
    }

    /**
     * Mentions chosen in the box are kept as Slack codes between U+E000 marks; the list shows them as names.
     */
    private function readableText(): string
    {
        return (string) preg_replace_callback(SendSlackMessage::MENTION, function (array $match) {
            $code = $match[1];

            return match ($code[0]) {
                '@'     => '@'.(SlackUser::where('slack_id', substr($code, 1))->value('display_name') ?: SlackUser::where('slack_id', substr($code, 1))->value('real_name') ?: substr($code, 1)),
                '#'     => '#'.(SlackChannel::where('slack_id', substr($code, 1))->value('name') ?? substr($code, 1)),
                default => '@'.substr($code, 1),
            };
        }, $this->text);
    }
}
