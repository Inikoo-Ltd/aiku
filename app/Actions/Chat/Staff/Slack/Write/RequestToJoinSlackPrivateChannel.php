<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Nov 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack\Write;

use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackUser;
use App\Models\SysAdmin\User;
use App\Notifications\SlackJoinRequestNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Ask to join a private channel its members chose to list: the members linked to aiku are told, and one of them adds the
 * person in Slack; the member sync then opens it. Asking again within a week tells nobody twice.
 */
class RequestToJoinSlackPrivateChannel
{
    use AsAction;

    public const int REMIND_AFTER_DAYS = 7;

    public function authorize(ActionRequest $request): bool
    {
        return $request->route('slackChannel')->isListedTo($request->user());
    }

    public function handle(SlackChannel $slackChannel, User $user): int
    {
        $requestedAt = DB::table('slack_channel_join_requests')->where('slack_channel_id', $slackChannel->id)->where('user_id', $user->id)->value('requested_at');
        if ($requestedAt && now()->subDays(self::REMIND_AFTER_DAYS)->lt($requestedAt)) {
            return 0;
        }
        DB::table('slack_channel_join_requests')->updateOrInsert(['slack_channel_id' => $slackChannel->id, 'user_id' => $user->id], ['requested_at' => now()]);

        $members = User::whereIn('id', SlackUser::whereIn('slack_id', $slackChannel->members()->select('slack_user_id'))->whereNotNull('user_id')->select('user_id'))
            ->where('status', true)
            ->get()
            ->filter(fn (User $member) => $slackChannel->isPrivateVisibleTo($member));

        $notification = new SlackJoinRequestNotification(
            __(':name asks to join :channel', ['name' => $user->chatName(), 'channel' => $slackChannel->name]),
            __('Add them in Slack if they should be in it. They will see the channel in Verba once they are a member.'),
            route('grp.chat.staff.groups.slack.show', $slackChannel->id)
        );
        $members->each(fn (User $member) => $member->notify($notification));

        return $members->count();
    }

    public function asController(SlackChannel $slackChannel, ActionRequest $request): RedirectResponse
    {
        $this->handle($slackChannel, $request->user());

        return back();
    }
}
