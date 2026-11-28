<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Nov 2026 15:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Scheduled;

use App\Actions\Chat\Staff\SendStaffMessage;
use App\Actions\Chat\Staff\Slack\Write\SendSlackMessage;
use App\Http\Resources\Chat\StaffMessageScheduleResource;
use App\Models\Chat\SlackMessage;
use App\Models\Chat\StaffMessageSchedule;
use App\Notifications\SlackJoinRequestNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * Sends one scheduled message, from the minute job when it is due or at once from "Send now". The row is claimed
 * (pending → sending, committed) before anything is posted, so overlapping runs and a click at the same minute send it
 * once; a row that loses the claim is left alone. Everything is checked again by the send action itself, and a refusal
 * is kept on the row and rung on the person's bell, never dropped or retried: Slack may have posted a message that did
 * not answer.
 */
class SendStaffMessageSchedule
{
    use AsAction;

    public function handle(StaffMessageSchedule $schedule, bool $now = false): bool
    {
        $claimed = StaffMessageSchedule::whereKey($schedule->id)
            ->where('state', StaffMessageSchedule::PENDING)
            ->when(!$now, fn ($query) => $query->where('send_at', '<=', now()))
            ->update(['state' => StaffMessageSchedule::SENDING, 'claimed_at' => now()]);
        if ($claimed === 0) {
            return false;
        }
        $schedule->refresh();

        try {
            $schedule->slack_channel_id ? $this->sendToSlack($schedule) : $this->sendToVerba($schedule);
        } catch (ValidationException $exception) {
            $this->fail($schedule, Arr::first(Arr::flatten($exception->errors())) ?? __('The message was not sent.'));

            return false;
        } catch (Throwable $exception) {
            report($exception);
            $this->fail($schedule, __('Sending stopped part way. Check the conversation before sending it again.'));

            return false;
        }

        return true;
    }

    private function sendToSlack(StaffMessageSchedule $schedule): void
    {
        $sent = SendSlackMessage::make()->handle(
            $schedule->user,
            $schedule->slackChannel,
            $schedule->text,
            $schedule->thread_ts,
            'scheduled-'.$schedule->id,
            $schedule->also_to_channel,
            $schedule->notify_everyone
        );

        self::finish($schedule, [
            'state'            => StaffMessageSchedule::SENT,
            'sent_at'          => now(),
            'slack_message_id' => isset($sent['ts']) ? SlackMessage::where('slack_channel_id', $schedule->slack_channel_id)->where('ts', $sent['ts'])->value('id') : null,
        ]);
    }

    private function sendToVerba(StaffMessageSchedule $schedule): void
    {
        $conversation = $schedule->staffConversation;
        ScheduleStaffMessage::ensureSendable($schedule->user, null, $conversation, $schedule->text, false, null, $schedule->parent_id);

        $message = SendStaffMessage::run($conversation, $schedule->user, array_filter(['body' => $schedule->text, 'parent_id' => $schedule->parent_id]));

        self::finish($schedule, ['state' => StaffMessageSchedule::SENT, 'sent_at' => now(), 'staff_message_id' => $message->id]);
    }

    /**
     * The bell says where and why, never the words: a private chat's text stays out of the notifications table.
     */
    public static function fail(StaffMessageSchedule $schedule, string $reason): void
    {
        if (!self::finish($schedule, ['state' => StaffMessageSchedule::FAILED, 'failure_reason' => $reason])) {
            return;
        }

        $target = (new StaffMessageScheduleResource($schedule))->toArray(request())['target'];
        rescue(fn () => $schedule->user->notify(new SlackJoinRequestNotification(
            __('Your scheduled message to :target was not sent', ['target' => $target['name']]),
            $reason,
            $target['url'],
            'verba_scheduled_failed'
        )));
    }

    /**
     * Only a row still being sent is closed, so a slow send and the stuck-row sweep cannot overwrite each other.
     *
     * @param array<string, mixed> $changes
     */
    private static function finish(StaffMessageSchedule $schedule, array $changes): bool
    {
        $closed = StaffMessageSchedule::whereKey($schedule->id)->where('state', StaffMessageSchedule::SENDING)->update([...$changes, 'updated_at' => now()]);
        $schedule->refresh();

        return $closed > 0;
    }

    public function asController(StaffMessageSchedule $staffMessageSchedule, Request $request): StaffMessageScheduleResource
    {
        abort_unless($staffMessageSchedule->user_id === $request->user()->id, 404);
        if ($staffMessageSchedule->state !== StaffMessageSchedule::PENDING) {
            throw ValidationException::withMessages(['schedule' => __('This message is no longer waiting to be sent.')]);
        }

        $this->handle($staffMessageSchedule, now: true);

        return new StaffMessageScheduleResource($staffMessageSchedule->refresh());
    }
}
