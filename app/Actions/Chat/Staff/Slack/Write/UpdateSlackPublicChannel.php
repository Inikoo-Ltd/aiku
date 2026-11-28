<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Nov 2026 20:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack\Write;

use App\Actions\Chat\Staff\Slack\UpdateSlackChannelFromEvent;
use App\Actions\Chat\Staff\Slack\UpdateSlackChannelMember;
use App\Actions\Chat\Staff\Slack\SlackWorkspace;
use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackUser;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Join, leave, add people to, rename, set the topic or description of, and archive a public channel from aiku (INI-467),
 * each with the person's own token. Slack's answer is kept at once through the code the channel and member events use,
 * so Slack's echo changes nothing. Archiving stops new messages; the history stays readable.
 */
class UpdateSlackPublicChannel
{
    use AsAction;
    use WithSlackChannelWrite;

    public const array OPERATIONS = ['join', 'leave', 'invite', 'rename', 'topic', 'purpose', 'archive'];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'operation'        => ['required', Rule::in(self::OPERATIONS)],
            'name'             => ['exclude_unless:operation,rename', 'required_if:operation,rename', ...array_slice(self::nameRules(), 1)],
            'text'             => ['present_if:operation,topic,purpose', 'nullable', 'string', 'max:250'],
            'slack_user_ids'   => ['required_if:operation,invite', 'array', 'max:100'],
            'slack_user_ids.*' => ['string', 'max:32'],
        ];
    }

    /**
     * @param  array{operation: string, name?: string, text?: string|null, slack_user_ids?: list<string>}  $attributes
     */
    public function handle(User $user, SlackChannel $slackChannel, array $attributes): SlackChannel
    {
        $this->ensureMayWrite($user, $slackChannel);
        $client  = $this->clientFor($user, channel: $slackChannel);
        $channel = ['channel' => $slackChannel->slack_id];

        switch ($attributes['operation']) {
            case 'join':
                $this->callChannel($client, 'conversations.join', $channel);
                $this->member($slackChannel, $client->slackUserId(), true);
                break;
            case 'leave':
                $this->callChannel($client, 'conversations.leave', $channel, ['not_in_channel']);
                $this->member($slackChannel, $client->slackUserId(), false);
                break;
            case 'invite':
                $people = array_values(array_diff($this->invitees($slackChannel, $attributes['slack_user_ids'] ?? []), $slackChannel->members()->pluck('slack_user_id')->all()));
                if ($people === []) {
                    break;
                }
                $answer  = $this->callChannel($client, 'conversations.invite', $channel + ['users' => implode(',', $people), 'force' => true], ['already_in_channel']);
                $refused = Arr::pluck((array) Arr::get($answer, 'errors', []), 'user');
                foreach (array_diff($people, $refused) as $slackUserId) {
                    $this->member($slackChannel, $slackUserId, true);
                }
                if ($refused !== []) {
                    $this->refuse(__('Slack could not add: :names', ['names' => $this->names($refused)]));
                }
                break;
            case 'rename':
                $answer = $this->callChannel($client, 'conversations.rename', $channel + ['name' => $attributes['name']]);
                UpdateSlackChannelFromEvent::make()->handle(['type' => 'channel_rename', 'channel' => ['id' => $slackChannel->slack_id, 'name' => Arr::get($answer, 'channel.name', $attributes['name'])]], null);
                break;
            case 'topic':
                $answer = $this->callChannel($client, 'conversations.setTopic', $channel + ['topic' => (string) $attributes['text']]);
                $this->keepChannelData($slackChannel->refresh(), $answer, 'topic', (string) $attributes['text']);
                break;
            case 'purpose':
                $answer = $this->callChannel($client, 'conversations.setPurpose', $channel + ['purpose' => (string) $attributes['text']]);
                $this->keepChannelData($slackChannel->refresh(), $answer, 'purpose', (string) $attributes['text']);
                break;
            case 'archive':
                $this->callChannel($client, 'conversations.archive', $channel, ['already_archived']);
                UpdateSlackChannelFromEvent::make()->handle(['type' => 'channel_archive', 'channel' => $slackChannel->slack_id], null);
                break;
        }

        return $slackChannel->refresh();
    }

    /**
     * Live people of this channel's workspace, not bots; anyone else is refused before Slack is asked.
     *
     * @param  list<string>  $slackUserIds
     * @return list<string>
     */
    private function invitees(SlackChannel $slackChannel, array $slackUserIds): array
    {
        $otherTeams = array_values(array_filter(array_map(fn (SlackWorkspace $workspace) => $workspace->teamId, SlackWorkspace::others())));
        $people     = SlackUser::whereIn('slack_id', $slackUserIds)->where('is_bot', false)->where('is_deleted', false)
            ->when(
                $slackChannel->slack_team_id,
                fn ($query) => $query->where('data->team_id', $slackChannel->slack_team_id),
                fn ($query) => $query->where(fn ($query) => $query->whereNull('data->team_id')->orWhereNotIn('data->team_id', $otherTeams ?: ['']))
            )
            ->pluck('slack_id')->all();
        $missing = array_diff(array_unique($slackUserIds), $people);
        if ($people === [] || $missing !== []) {
            $this->refuse(__('Slack could not add: :names', ['names' => $this->names($missing ?: $slackUserIds)]));
        }

        return $people;
    }

    /**
     * @param  array<int, string>  $slackUserIds
     */
    private function names(array $slackUserIds): string
    {
        $known = SlackUser::whereIn('slack_id', $slackUserIds)->get(['slack_id', 'display_name', 'real_name'])->keyBy('slack_id');

        return collect($slackUserIds)->map(fn (string $id) => $known->get($id)?->display_name ?: $known->get($id)?->real_name ?: $id)->implode(', ');
    }

    private function member(SlackChannel $slackChannel, string $slackUserId, bool $joined): void
    {
        UpdateSlackChannelMember::make()->handle(['type' => $joined ? 'member_joined_channel' : 'member_left_channel', 'user' => $slackUserId, 'channel' => $slackChannel->slack_id]);
    }

    /**
     * @return array{ok: true, member_count: int}
     */
    public function asController(SlackChannel $slackChannel, ActionRequest $request): array
    {
        $channel = $this->handle($request->user(), $slackChannel, $request->validated());

        return ['ok' => true, 'member_count' => $channel->members()->count()];
    }
}
