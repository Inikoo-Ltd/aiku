<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Nov 2026 15:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Scheduled;

use App\Actions\Chat\Staff\Json\SearchSlackMessages;
use App\Models\Chat\StaffMessageReminder;
use App\Models\Chat\StaffMessageSchedule;
use App\Notifications\SlackJoinRequestNotification;
use Illuminate\Console\Command;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Every minute: sends the Verba messages that are due, rings the reminders that are due, and marks failed a message
 * left half way (claimed but never finished, e.g. the worker died while Slack was answering). Such a message may be in
 * Slack already, so it is never sent again on its own.
 */
class SendDueStaffMessages
{
    use AsAction;

    public string $commandSignature = 'verba:send-due';

    public string $commandDescription = 'Send scheduled Verba messages and ring message reminders that are due';

    public const int STUCK_MINUTES = 10;

    public const int BATCH = 200;

    /**
     * @return array{sent: int, failed: int, reminded: int}
     */
    public function handle(): array
    {
        $counts = ['sent' => 0, 'failed' => 0, 'reminded' => 0];

        StaffMessageSchedule::where('state', StaffMessageSchedule::SENDING)
            ->where('claimed_at', '<', now()->subMinutes(self::STUCK_MINUTES))
            ->get()
            ->each(function (StaffMessageSchedule $schedule) use (&$counts) {
                SendStaffMessageSchedule::fail($schedule, __('Sending stopped part way. Check the conversation before sending it again.'));
                $counts['failed']++;
            });

        StaffMessageSchedule::where('state', StaffMessageSchedule::PENDING)
            ->where('send_at', '<=', now())
            ->orderBy('send_at')
            ->limit(self::BATCH)
            ->get()
            ->each(function (StaffMessageSchedule $schedule) use (&$counts) {
                if (SendStaffMessageSchedule::run($schedule)) {
                    $counts['sent']++;
                } elseif ($schedule->refresh()->state === StaffMessageSchedule::FAILED) {
                    $counts['failed']++;
                }
            });

        StaffMessageReminder::whereNull('rung_at')
            ->where('remind_at', '<=', now())
            ->orderBy('remind_at')
            ->limit(self::BATCH)
            ->get()
            ->each(function (StaffMessageReminder $reminder) use (&$counts) {
                if ($this->ring($reminder)) {
                    $counts['reminded']++;
                }
            });

        return $counts;
    }

    /**
     * Claimed by rung_at before the bell, so it rings once. A message deleted since, or one the person may no longer
     * read, rings nothing. The bell names who wrote it and where, never the words.
     */
    public function ring(StaffMessageReminder $reminder): bool
    {
        if (StaffMessageReminder::whereKey($reminder->id)->whereNull('rung_at')->update(['rung_at' => now()]) === 0) {
            return false;
        }

        $user    = $reminder->user;
        $message = $reminder->slackMessage ?? $reminder->staffMessage;
        if (!StoreStaffMessageReminder::mayRemind($user, $message)) {
            return false;
        }

        if ($reminder->slackMessage) {
            $channel = $reminder->slackMessage->channel;
            $where   = $channel->direct_type ? __('a Slack direct message') : '#'.$channel->name;
            $author  = $reminder->slackMessage->user?->chatName();
            $url     = SearchSlackMessages::messageUrl($reminder->slackMessage);
        } else {
            $conversation = $reminder->staffMessage->conversation;
            $where        = $conversation->name ?: __('your Verba chat');
            $author       = $reminder->staffMessage->user?->chatName();
            $url          = route($conversation->isVerbration() ? 'grp.chat.staff.groups.show' : 'grp.chat.staff.show', $conversation->ulid);
        }

        $user->notify(new SlackJoinRequestNotification(
            __('Reminder: a message in :where', ['where' => $where]),
            $author ? __('From :name', ['name' => $author]) : __('Open it to read it again.'),
            $url,
            'verba_message_reminder'
        ));

        return true;
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();
        $counts = $this->handle();
        $command->info("{$counts['sent']} sent, {$counts['failed']} failed, {$counts['reminded']} reminders rung");

        return 0;
    }
}
