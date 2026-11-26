<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Nov 2026 23:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack\Write;

use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackConnection;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * New Verbration (INI-564): a real Slack private channel created with the person's own token (groups:write, part of the private
 * opt-in), the chosen people invited, and stored at once as a private channel with its members. Only for a person who opted in.
 */
class StoreSlackPrivateChannel
{
    use AsAction;

    public function authorize(ActionRequest $request): bool
    {
        return SlackWriteAccess::allows($request->user());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name'       => ['required', 'string', 'max:80'],
            'user_ids'   => ['required', 'array', 'min:1', 'max:200'],
            'user_ids.*' => ['integer'],
        ];
    }

    public function asController(ActionRequest $request): JsonResponse
    {
        $channel = $this->handle($request->user(), $request->validated('name'), $request->validated('user_ids'));

        return response()->json(['id' => $channel->id, 'url' => route('grp.chat.staff.groups.slack.show', $channel->id)]);
    }

    /**
     * @param  list<int>  $userIds
     */
    public function handle(User $user, string $name, array $userIds): SlackChannel
    {
        $client = SlackConnection::where('user_id', $user->id)->first()?->hasPrivateConsent() ? SlackWriteClient::for($user) : null;
        if (!$client) {
            $this->refuse(__('Bring your private conversations to Verba first (your profile, Slack section): a Verbration is a Slack private channel created as you'));
        }
        $slackName = Str::of($name)->ascii()->lower()->replaceMatches('/[^a-z0-9_-]+/', '-')->trim('-')->limit(80, '')->toString();
        if ($slackName === '') {
            $this->refuse(__('Use letters or numbers in the name'));
        }

        $invitees = User::whereIn('id', $userIds)->where('group_id', $user->group_id)->where('status', true)->whereKeyNot($user->id)->get()
            ->reject(fn (User $invitee) => $invitee->worksOnlyForAgents());
        if ($invitees->isEmpty()) {
            $this->refuse(__('Choose at least one other person'), 'user_ids');
        }
        $people     = $invitees->mapWithKeys(fn (User $invitee) => [$invitee->id => SlackChannel::linkedSlackPerson($invitee)?->slack_id]);
        $notOnSlack = $invitees->filter(fn (User $invitee) => !$people[$invitee->id]);
        if ($notOnSlack->isNotEmpty()) {
            $this->refuse(__('Not on Slack, so they cannot be invited: :names', ['names' => $notOnSlack->map(fn (User $invitee) => $invitee->chatName())->implode(', ')]), 'user_ids');
        }

        try {
            $slackChannel = (array) Arr::get($client->call('conversations.create', ['name' => $slackName, 'is_private' => true]), 'channel', []);
        } catch (SlackWriteException $exception) {
            $this->refuse(match ($exception->slackError) {
                'name_taken'                        => __('Slack already has a channel called :name. Choose another name', ['name' => $slackName]),
                'invalid_name', 'invalid_name_specials', 'invalid_name_maxlength' => __('Slack does not accept that name. Use letters, numbers and hyphens'),
                'restricted_action'                 => __('Your Slack workspace does not let you create private channels'),
                default                             => __('Slack did not create the channel (:error). Try again', ['error' => $exception->slackError]),
            });
        }

        $channel = DB::transaction(function () use ($user, $slackChannel, $client) {
            $channel = SlackChannel::firstOrCreate(['slack_id' => (string) Arr::get($slackChannel, 'id')], [
                'group_id'           => $user->group_id,
                'brought_by_user_id' => $user->id,
                'name'               => Arr::get($slackChannel, 'name'),
                'is_archived'       => false,
                'is_member'         => false,
                'is_public'         => false,
                'is_private'        => true,
                'data'              => $slackChannel,
                'history_synced_at' => now(),
            ]);
            $channel->members()->firstOrCreate(['slack_user_id' => $client->slackUserId()]);

            return $channel;
        });

        try {
            $client->call('conversations.invite', ['channel' => $channel->slack_id, 'users' => $people->values()->implode(',')]);
        } catch (SlackWriteException $exception) {
            $this->refuse(__('The Verbration was created, but Slack did not add the people (:error). Add them in Slack', ['error' => $exception->slackError]), 'user_ids');
        }
        foreach ($people as $slackUserId) {
            $channel->members()->firstOrCreate(['slack_user_id' => $slackUserId]);
        }

        return $channel;
    }

    private function refuse(string $message, string $field = 'name'): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
