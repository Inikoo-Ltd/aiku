<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Nov 2026 15:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Scheduled;

use App\Actions\Chat\Staff\Slack\Write\SendSlackMessage;
use App\Http\Resources\Chat\StaffMessageScheduleResource;
use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackMessage;
use App\Models\Chat\StaffConversation;
use App\Models\Chat\StaffMessageSchedule;
use App\Models\SysAdmin\User;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * "Send later" in the Verba box (INI-471). The target, thread and text pass the same checks as an immediate send, and
 * a Slack @here/@channel asks its confirmation now, so the minute job never stops on a question nobody can answer. The
 * time is the wall clock the person picked in their own time zone (User::timezone_name), kept in UTC.
 */
class ScheduleStaffMessage
{
    use AsAction;

    public const int MAX_STAFF_LENGTH = 5000;

    public const int MAX_DAYS_AHEAD = 120;

    public const int MAX_PENDING_PER_PERSON = 100;

    /**
     * @param array{text: string, send_at: string, thread_ts?: string|null, parent_id?: int|null, also_to_channel?: bool, notify_everyone?: bool} $modelData
     */
    public function handle(User $user, SlackChannel|StaffConversation $target, array $modelData): StaffMessageSchedule
    {
        if (StaffMessageSchedule::where('user_id', $user->id)->where('state', StaffMessageSchedule::PENDING)->count() >= self::MAX_PENDING_PER_PERSON) {
            throw ValidationException::withMessages(['send_at' => __('You have too many scheduled messages. Send or cancel some first.')]);
        }

        $isSlack   = $target instanceof SlackChannel;
        $text      = trim($modelData['text']);
        $threadTs  = $isSlack ? ($modelData['thread_ts'] ?? null) : null;
        $parentId  = $isSlack ? null : ($modelData['parent_id'] ?? null);
        $notifyAll = $isSlack && ($modelData['notify_everyone'] ?? false);

        self::ensureSendable($user, $isSlack ? $target : null, $isSlack ? null : $target, $text, $notifyAll, $threadTs, $parentId);

        return StaffMessageSchedule::create([
            'group_id'              => $user->group_id,
            'user_id'               => $user->id,
            'slack_channel_id'      => $isSlack ? $target->id : null,
            'staff_conversation_id' => $isSlack ? null : $target->id,
            'thread_ts'             => $threadTs,
            'parent_id'             => $parentId,
            'text'                  => $text,
            'also_to_channel'       => $isSlack && $threadTs && ($modelData['also_to_channel'] ?? false),
            'notify_everyone'       => $notifyAll,
            'send_at'               => self::sendAt($user, $modelData['send_at']),
            'state'                 => StaffMessageSchedule::PENDING,
        ]);
    }

    /**
     * Asked when scheduling and when editing; the send itself asks it all again through the send action.
     */
    public static function ensureSendable(User $user, ?SlackChannel $slackChannel, ?StaffConversation $conversation, string $text, bool $notifyEveryone, ?string $threadTs = null, ?int $parentId = null): void
    {
        if ($text === '') {
            throw ValidationException::withMessages(['text' => __('Write a message first.')]);
        }

        if ($slackChannel) {
            $mayWrite = $slackChannel->direct_type ? SendSlackMessage::mayWriteDirect($user, $slackChannel) : SendSlackMessage::mayWrite($user, $slackChannel);
            if (!$mayWrite || !SlackChannel::kept()->whereKey($slackChannel->id)->exists()) {
                throw ValidationException::withMessages(['slack' => __('Messages cannot be sent to this channel.')]);
            }
            if (mb_strlen(SendSlackMessage::toSlackText($text)) > SendSlackMessage::MAX_LENGTH) {
                throw ValidationException::withMessages(['text' => __('The message is too long for Slack, keep it under :max characters.', ['max' => number_format(SendSlackMessage::MAX_LENGTH)])]);
            }
            if ($threadTs && !SlackMessage::where('slack_channel_id', $slackChannel->id)->where('ts', $threadTs)->exists()) {
                throw ValidationException::withMessages(['slack' => __('This thread is no longer in Slack.')]);
            }
            SendSlackMessage::make()->withMentions($text, $user, $slackChannel, $notifyEveryone);

            return;
        }

        if (!$user->status || !$conversation?->canBeWrittenToBy($user)) {
            throw ValidationException::withMessages(['text' => __('You cannot write in this conversation.')]);
        }
        if (mb_strlen($text) > self::MAX_STAFF_LENGTH) {
            throw ValidationException::withMessages(['text' => __('The message is too long, keep it under :max characters.', ['max' => number_format(self::MAX_STAFF_LENGTH)])]);
        }
        if ($parentId && !$conversation->messages()->whereKey($parentId)->exists()) {
            throw ValidationException::withMessages(['parent_id' => __('The message you reply to is no longer there.')]);
        }
    }

    /**
     * A wall-clock time ("2026-11-29 09:00") read in the person's own time zone.
     */
    public static function sendAt(User $user, string $localTime, string $field = 'send_at'): Carbon
    {
        try {
            $at = Carbon::createFromFormat('Y-m-d H:i', $localTime, $user->timezone_name)->utc();
        } catch (InvalidFormatException) {
            throw ValidationException::withMessages([$field => __('Choose a date and time.')]);
        }
        if ($at->lte(now())) {
            throw ValidationException::withMessages([$field => __('Choose a time in the future.')]);
        }
        if ($at->gt(now()->addDays(self::MAX_DAYS_AHEAD))) {
            throw ValidationException::withMessages([$field => __('Choose a time within the next :days days.', ['days' => self::MAX_DAYS_AHEAD])]);
        }

        return $at;
    }

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'text'            => ['required', 'string', 'max:'.self::MAX_STAFF_LENGTH],
            'send_at'         => ['required', 'string', 'max:16'],
            'thread_ts'       => ['sometimes', 'nullable', 'string', 'regex:/^\d{1,12}\.\d{1,8}$/'],
            'parent_id'       => ['sometimes', 'nullable', 'integer'],
            'also_to_channel' => ['sometimes', 'boolean'],
            'notify_everyone' => ['sometimes', 'boolean'],
        ];
    }

    public function inSlackChannel(SlackChannel $slackChannel, Request $request): StaffMessageScheduleResource
    {
        return new StaffMessageScheduleResource($this->handle($request->user(), $slackChannel, $request->validate(self::rules())));
    }

    public function inConversation(StaffConversation $staffConversation, Request $request): StaffMessageScheduleResource
    {
        return new StaffMessageScheduleResource($this->handle($request->user(), $staffConversation, $request->validate(self::rules())));
    }
}
