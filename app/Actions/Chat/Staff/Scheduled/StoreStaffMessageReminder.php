<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Nov 2026 15:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Scheduled;

use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackMessage;
use App\Models\Chat\StaffMessage;
use App\Models\Chat\StaffMessageReminder;
use App\Models\SysAdmin\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * "Remind me about this message": one bell at the chosen time (the person's own time zone), on a message they may read.
 */
class StoreStaffMessageReminder
{
    use AsAction;

    public const int MAX_WAITING_PER_PERSON = 200;

    public function handle(User $user, SlackMessage|StaffMessage $message, string $remindAt): StaffMessageReminder
    {
        if (StaffMessageReminder::where('user_id', $user->id)->whereNull('rung_at')->count() >= self::MAX_WAITING_PER_PERSON) {
            throw ValidationException::withMessages(['remind_at' => __('You have too many reminders waiting.')]);
        }

        return StaffMessageReminder::create([
            'user_id'          => $user->id,
            'slack_message_id' => $message instanceof SlackMessage ? $message->id : null,
            'staff_message_id' => $message instanceof StaffMessage ? $message->id : null,
            'remind_at'        => ScheduleStaffMessage::sendAt($user, $remindAt, 'remind_at'),
        ]);
    }

    public static function mayRemind(User $user, SlackMessage|StaffMessage|null $message): bool
    {
        if (!$message || $message->trashed() || !$user->status) {
            return false;
        }

        return $message instanceof SlackMessage
            ? (bool) $message->channel?->isReadableBy($user)
            : (bool) $message->conversation?->canBeAccessedBy($user);
    }

    /**
     * @return array{remind_at: string}
     */
    private function answer(User $user, StaffMessageReminder $reminder): array
    {
        return ['remind_at' => $reminder->remind_at->copy()->setTimezone($user->timezone_name)->format('Y-m-d H:i')];
    }

    public function inSlackChannel(SlackChannel $slackChannel, Request $request): array
    {
        $data    = $request->validate(['ts' => ['required', 'string', 'regex:/^\d{1,12}\.\d{1,8}$/'], 'remind_at' => ['required', 'string', 'max:16']]);
        $message = SlackMessage::where('slack_channel_id', $slackChannel->id)->where('ts', $data['ts'])->first();
        abort_unless(self::mayRemind($request->user(), $message), 404);

        return $this->answer($request->user(), $this->handle($request->user(), $message, $data['remind_at']));
    }

    public function inStaffMessage(StaffMessage $staffMessage, Request $request): array
    {
        $data = $request->validate(['remind_at' => ['required', 'string', 'max:16']]);
        abort_unless(self::mayRemind($request->user(), $staffMessage), 404);

        return $this->answer($request->user(), $this->handle($request->user(), $staffMessage, $data['remind_at']));
    }
}
