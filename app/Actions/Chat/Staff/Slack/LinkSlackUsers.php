<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 24 Nov 2026 13:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack;

use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackUser;
use App\Models\SysAdmin\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class LinkSlackUsers
{
    use AsAction;

    public string $commandSignature = 'slack:link-users {pairs* : slack=username, the Slack handle or id and the aiku username} {--by= : aiku username of the engineer confirming} {--confirm : write the links; without it nothing is written} {--undo : remove links confirmed by hand instead}';

    public string $commandDescription = 'Link aiku users to their Slack person by hand when the emails differ; shows what it would do unless --confirm';

    /**
     * @param  array<int, string>  $pairs
     * @return array<int, array{pair: string, slack_user: ?SlackUser, user: ?User, action: string}>
     */
    public function plan(array $pairs, bool $undo): array
    {
        return array_map(function (string $pair) use ($undo) {
            [$slackRef, $username] = array_pad(array_map('trim', explode('=', $pair, 2)), 2, '');
            $row = ['pair' => $pair, 'slack_user' => null, 'user' => null, 'action' => ''];

            $people = SlackUser::where(fn ($query) => $query->where('slack_id', $slackRef)->orWhereRaw('lower(name) = lower(?)', [$slackRef]))->get();
            if ($people->count() !== 1) {
                return [...$row, 'action' => $people->isEmpty() ? 'refused: no Slack person '.$slackRef : 'refused: several Slack people are '.$slackRef.', use the Slack id'];
            }
            $slackUser = $row['slack_user'] = $people->first();

            if ($undo) {
                return [...$row, 'user' => $slackUser->user, 'action' => $slackUser->link_confirmed_at ? 'unlink' : 'refused: not linked by hand'];
            }
            if ($slackUser->is_bot || $slackUser->is_deleted || ($slackUser->data['is_restricted'] ?? false)) {
                return [...$row, 'action' => 'refused: Slack person is a bot, a leaver or a guest'];
            }

            $user = $row['user'] = User::whereRaw('lower(username) = lower(?)', [$username])->first();
            if (!$user || !$user->status) {
                return [...$row, 'action' => 'refused: no active aiku user '.$username];
            }
            $current = SlackChannel::linkedSlackPerson($user);
            if ($current?->is($slackUser)) {
                return [...$row, 'action' => 'already linked'];
            }
            if ($current?->link_confirmed_at) {
                return [...$row, 'action' => 'refused: '.$user->username.' is already linked by hand to Slack person '.$current->name];
            }
            if ($slackUser->user_id && $slackUser->user_id !== $user->id && $slackUser->link_confirmed_at) {
                return [...$row, 'action' => 'refused: Slack person is linked by hand to '.$slackUser->user?->username];
            }

            return [...$row, 'action' => $current ? 'link, replaces email match '.$current->name : 'link'];
        }, $pairs);
    }

    public function link(SlackUser $slackUser, User $user, User $by): void
    {
        $replaced = SlackChannel::linkedSlackPerson($user);
        DB::transaction(function () use ($slackUser, $user, $by, $replaced) {
            $slackUser->update(['user_id' => $user->id, 'link_confirmed_at' => now(), 'link_confirmed_by_user_id' => $by->id]);
            if (!$user->slack_user_id || $user->slack_user_id === $replaced?->slack_id) {
                $user->update(['slack_user_id' => $slackUser->slack_id]);
            }
        });
        StoreSlackUser::make()->linkMessages($slackUser->slack_id);
    }

    public function unlink(SlackUser $slackUser): void
    {
        DB::transaction(function () use ($slackUser) {
            if ($slackUser->user_id) {
                DB::table('slack_messages')->where('slack_user_id', $slackUser->slack_id)->where('user_id', $slackUser->user_id)->update(['user_id' => null]);
                User::whereKey($slackUser->user_id)->where('slack_user_id', $slackUser->slack_id)->update(['slack_user_id' => null]);
            }
            $slackUser->update(['user_id' => null, 'link_confirmed_at' => null, 'link_confirmed_by_user_id' => null]);
        });
    }

    public function asCommand(Command $command): int
    {
        $undo = (bool) $command->option('undo');
        $plan = $this->plan($command->argument('pairs'), $undo);

        $command->table(['Pair', 'Slack person', 'Slack email', 'aiku user', 'aiku email', 'Action'], array_map(fn (array $row) => [
            $row['pair'],
            $row['slack_user'] ? trim($row['slack_user']->name.' ('.$row['slack_user']->real_name.')') : '',
            $row['slack_user']?->email,
            $row['user'] ? $row['user']->username.' ('.$row['user']->contact_name.')' : '',
            $row['user']?->email,
            $row['action'],
        ], $plan));

        if (!$command->option('confirm')) {
            $command->warn('Nothing written. Run again with --confirm --by=<your username> to apply the link/unlink rows.');

            return 0;
        }

        $by = User::where('username', (string) $command->option('by'))->where('status', true)->first();
        if (!$by && !$undo) {
            $command->error('--by must be the active aiku username of the engineer confirming');

            return 1;
        }

        $done = 0;
        foreach ($plan as $row) {
            if (str_starts_with($row['action'], 'link')) {
                $this->link($row['slack_user'], $row['user'], $by);
                $done++;
            } elseif ($row['action'] === 'unlink') {
                $this->unlink($row['slack_user']);
                $done++;
            }
        }
        $command->info(($undo ? 'Unlinked: ' : 'Linked: ').$done);

        return 0;
    }
}
