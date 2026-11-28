<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Nov 2026 15:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Scheduled;

use App\Actions\Chat\Staff\Slack\Write\SendSlackMessage;
use App\Http\Resources\Chat\StaffMessageScheduleResource;
use App\Models\Chat\StaffMessageSchedule;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Edits the text and time of a message still waiting, or cancels it. Both change the row only while it is pending, so
 * a message the minute job has already claimed is reported as gone, never changed under it.
 */
class UpdateStaffMessageSchedule
{
    use AsAction;

    /**
     * A message with mentions chosen in the box keeps its text, as the Scheduled list cannot show the chips: only its time changes.
     *
     * @param array{text?: string|null, send_at: string} $modelData
     */
    public function handle(StaffMessageSchedule $schedule, array $modelData): StaffMessageSchedule
    {
        $hasMentions = (bool) preg_match(SendSlackMessage::MENTION, $schedule->text);
        $text        = $hasMentions || !isset($modelData['text']) ? $schedule->text : trim($modelData['text']);
        ScheduleStaffMessage::ensureSendable($schedule->user, $schedule->slackChannel, $schedule->staffConversation, $text, $schedule->notify_everyone, $schedule->thread_ts, $schedule->parent_id);

        $this->whilePending($schedule, [
            'text'    => $text,
            'send_at' => ScheduleStaffMessage::sendAt($schedule->user, $modelData['send_at']),
        ]);

        return $schedule->refresh();
    }

    /**
     * A failed message is cancelled too, which takes it off the list once the person has read why.
     */
    public function cancel(StaffMessageSchedule $schedule): StaffMessageSchedule
    {
        $this->whilePending($schedule, ['state' => StaffMessageSchedule::CANCELLED], [StaffMessageSchedule::PENDING, StaffMessageSchedule::FAILED]);

        return $schedule->refresh();
    }

    /**
     * @param array<string, mixed> $changes
     * @param array<int, string> $states
     */
    private function whilePending(StaffMessageSchedule $schedule, array $changes, array $states = [StaffMessageSchedule::PENDING]): void
    {
        $changed = StaffMessageSchedule::whereKey($schedule->id)->whereIn('state', $states)->update([...$changes, 'updated_at' => now()]);
        if ($changed === 0) {
            throw ValidationException::withMessages(['schedule' => __('This message is no longer waiting to be sent.')]);
        }
    }

    public function asController(StaffMessageSchedule $staffMessageSchedule, Request $request): StaffMessageScheduleResource
    {
        abort_unless($staffMessageSchedule->user_id === $request->user()->id, 404);
        $modelData = $request->validate([
            'text'    => ['sometimes', 'nullable', 'string', 'max:'.ScheduleStaffMessage::MAX_STAFF_LENGTH],
            'send_at' => ['required', 'string', 'max:16'],
        ]);

        return new StaffMessageScheduleResource($this->handle($staffMessageSchedule, $modelData));
    }

    public function inCancel(StaffMessageSchedule $staffMessageSchedule, Request $request): StaffMessageScheduleResource
    {
        abort_unless($staffMessageSchedule->user_id === $request->user()->id, 404);

        return new StaffMessageScheduleResource($this->cancel($staffMessageSchedule));
    }
}
