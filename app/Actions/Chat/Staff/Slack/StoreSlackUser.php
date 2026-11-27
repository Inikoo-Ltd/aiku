<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 20 Nov 2026 16:15:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack;

use App\Models\Chat\SlackUser;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreSlackUser
{
    use AsAction;

    /**
     * A Slack user object, as users.list and the user_change and team_join events give it; the same person twice is one row.
     *
     * @param  array<string, mixed>  $member
     */
    public function handle(array $member): ?SlackUser
    {
        $slackId = Arr::get($member, 'id');
        if (!$slackId) {
            return null;
        }

        $email     = strtolower(trim((string) Arr::get($member, 'profile.email'))) ?: null;
        $slackUser = SlackUser::createOrFirst(['slack_id' => $slackId]);
        $slackUser->fill([
            'name'         => Arr::get($member, 'name'),
            'real_name'    => Arr::get($member, 'real_name') ?: Arr::get($member, 'profile.real_name'),
            'display_name' => Arr::get($member, 'profile.display_name') ?: null,
            'email'        => $email ?? $slackUser->email,
            'avatar_url'   => Arr::get($member, 'profile.image_192') ?: Arr::get($member, 'profile.image_72') ?: $slackUser->avatar_url,
            'is_bot'       => (bool) Arr::get($member, 'is_bot') || $slackId === 'USLACKBOT',
            'is_deleted'   => (bool) Arr::get($member, 'deleted'),
            'data'         => $member,
        ]);

        $user                = $slackUser->link_confirmed_at ? $slackUser->user : $this->matchingUser($slackId, $slackUser->is_bot ? null : $slackUser->email);
        $slackUser->user_id  = $user?->id;
        $slackUser->save();

        if ($user && !$user->slack_user_id && !$slackUser->is_bot && !$slackUser->is_deleted && !SlackWorkspace::isOtherTeam(Arr::get($member, 'team_id'))) {
            $user->update(['slack_user_id' => $slackId]);
        }
        $this->linkMessages($slackId);

        return $slackUser;
    }

    /**
     * An aiku user already holding the Slack id wins; otherwise the email, when exactly one aiku user has it.
     */
    private function matchingUser(string $slackId, ?string $email): ?User
    {
        if ($known = User::where('slack_user_id', $slackId)->first()) {
            return $known;
        }
        if (!$email) {
            return null;
        }
        $candidates = User::whereRaw('lower(email) = ?', [$email])->limit(2)->get();

        return $candidates->count() === 1 ? $candidates->first() : null;
    }

    /**
     * Gives stored messages without an author the aiku user their Slack author now matches, a bounded batch at a time.
     */
    public function linkMessages(?string $slackId = null): int
    {
        $onlyThisPerson = $slackId ? 'and su2.slack_id = ?' : '';
        $linked         = 0;

        do {
            $updated = DB::update(
                "update slack_messages m set user_id = su.user_id from slack_users su
                where su.slack_id = m.slack_user_id and m.id in (
                    select m2.id from slack_messages m2 join slack_users su2 on su2.slack_id = m2.slack_user_id
                    where m2.user_id is null and su2.user_id is not null $onlyThisPerson limit 5000
                )",
                array_filter([$slackId])
            );
            $linked += $updated;
        } while ($updated > 0);

        return $linked;
    }
}
