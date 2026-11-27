<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 12 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Ticket;

use App\Actions\Chat\Staff\Slack\StoreSlackMessage;
use App\Actions\Chat\Staff\Slack\StoreSlackPrivateMessage;
use App\Actions\Chat\Staff\Slack\StoreSlackDirectMessage;
use App\Actions\Chat\Staff\Slack\StoreSlackEmoji;
use App\Actions\Chat\Staff\Slack\StoreSlackMessageReaction;
use App\Actions\Chat\Staff\Slack\StoreSlackPin;
use App\Actions\Chat\Staff\Slack\UpdateSlackChannelFromEvent;
use App\Actions\Chat\Staff\Slack\UpdateSlackChannelMember;
use App\Actions\Chat\Staff\Slack\StoreSlackUser;
use App\Actions\Chat\Staff\Slack\SlackWorkspace;
use App\Models\Chat\SlackChannel;
use App\Actions\Helpers\Ticket\Concerns\WithSlack;
use App\Models\Helpers\Ticket;
use App\Models\SysAdmin\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class ReceiveSlackTicketReaction
{
    use AsAction;
    use WithSlack;

    public function handle(Group $group, string $channel, string $ts, ?string $reactedBy = null): ?Ticket
    {
        $existing = Ticket::where(
            fn ($query) => $query
            ->where(fn ($origin) => $origin->where('data->slack->ts', $ts)->where('data->slack->channel_id', $channel))
            ->orWhere(fn ($alert) => $alert->where('data->slack_alert->ts', $ts)->where('data->slack_alert->channel', $channel))
        )->first();
        if ($existing) {
            return $existing;
        }

        $client  = $this->slackClient();
        $message = $client?->get('conversations.history', ['channel' => $channel, 'latest' => $ts, 'oldest' => $ts, 'inclusive' => true, 'limit' => 1])->json('messages.0');
        if (!$message) {
            Log::warning('Slack ticket reaction: message not found', ['channel' => $channel, 'ts' => $ts]);

            return null;
        }

        $reporterId = Arr::get($message, 'user');
        if ($reporterId && !$this->slackUserToAikuUser($reporterId)) {
            $reporterId = $reactedBy;
        }
        [$subject, $description] = array_pad(explode("\n", trim((string) Arr::get($message, 'text', '')), 2), 2, null);

        return StoreTicketFromSlack::run($group, [
            'user_id'     => $reporterId ?: Arr::get($message, 'user'),
            'channel_id'  => $channel,
            'ts'          => $ts,
            'subject'     => $subject,
            'description' => $description,
            'files'       => Arr::get($message, 'files', []),
        ]);
    }

    public function asController(Request $request): JsonResponse
    {
        $workspace = $this->slackSigningWorkspace($request);
        abort_unless((bool) $workspace, 401);

        if ($request->input('type') === 'url_verification') {
            return response()->json(['challenge' => $request->input('challenge')]);
        }

        $event     = $request->input('event', []);
        $channelId = self::eventChannelId($event);
        if ($channelId && !$workspace->owns(SlackChannel::where('slack_id', $channelId)->first())) {
            return $workspace->isMain() ? $this->ticketsOnly($event) : response()->json(['ok' => true]);
        }
        if (!$workspace->isMain()) {
            return $this->fromOtherWorkspace($workspace, $event, $channelId, $request->input('authorizations.0.user_id'));
        }
        if (in_array(Arr::get($event, 'type'), ['user_change', 'team_join'], true)) {
            if (!SlackWorkspace::isOtherTeam(Arr::get($event, 'user.team_id'))) {
                StoreSlackUser::dispatch((array) Arr::get($event, 'user', []));
            }

            return response()->json(['ok' => true]);
        }
        if (Arr::get($event, 'type') === 'emoji_changed') {
            dispatch(fn () => StoreSlackEmoji::make()->fromEvent($event));

            return response()->json(['ok' => true]);
        }
        if (in_array(Arr::get($event, 'type'), ['pin_added', 'pin_removed'], true)) {
            dispatch(fn () => StoreSlackPin::make()->fromEvent($event));

            return response()->json(['ok' => true]);
        }
        if (in_array(Arr::get($event, 'type'), UpdateSlackChannelFromEvent::EVENTS, true)) {
            UpdateSlackChannelFromEvent::run($event, $request->input('authorizations.0.user_id'));
            if (in_array(Arr::get($event, 'type'), ['member_joined_channel', 'member_left_channel'], true)) {
                UpdateSlackChannelMember::dispatch($event);
            }

            return response()->json(['ok' => true]);
        }
        if (Arr::get($event, 'type') === 'message' && in_array(Arr::get($event, 'channel_type'), StoreSlackDirectMessage::TYPES, true)) {
            if ($request->input('authorizations.0.is_bot') === false) {
                rescue(fn () => StoreSlackDirectMessage::run($event, (array) $request->input('authorizations.0', [])), fn (Throwable $exception) => Log::warning('Slack direct message not stored', ['channel' => Arr::get($event, 'channel'), 'ts' => Arr::get($event, 'ts'), 'error' => $exception::class]), false);
            }

            return response()->json(['ok' => true]);
        }
        if (Arr::get($event, 'type') === 'message') {
            if (Arr::get($event, 'subtype') === 'channel_convert_to_private' || Arr::get($event, 'channel_type') === 'group') {
                UpdateSlackChannelFromEvent::make()->markPrivate((string) Arr::get($event, 'channel'));
            }
            dispatch(fn () => StoreTicketCommentFromSlackThread::run($event));
            if (Arr::get($event, 'channel_type') === 'group') {
                rescue(fn () => StoreSlackPrivateMessage::run($event), fn (Throwable $exception) => Log::warning('Slack private message not stored', ['channel' => Arr::get($event, 'channel'), 'ts' => Arr::get($event, 'ts'), 'error' => $exception::class]), false);
            } elseif (Arr::get($event, 'subtype') !== 'channel_convert_to_private') {
                StoreSlackMessage::dispatch($event);
            }

            return response()->json(['ok' => true]);
        }
        if (in_array(Arr::get($event, 'type'), ['reaction_added', 'reaction_removed'], true)) {
            StoreSlackMessageReaction::dispatch($event);
        }
        if (
            Arr::get($event, 'type') !== 'reaction_added'
            || Arr::get($event, 'reaction') !== config('services.slack.ticket_reaction')
            || Arr::get($event, 'item.type') !== 'message'
        ) {
            return response()->json(['ok' => true]);
        }

        $group = Group::firstOrFail();
        dispatch(fn () => $this->handle($group, Arr::get($event, 'item.channel'), Arr::get($event, 'item.ts'), Arr::get($event, 'user')));

        return response()->json(['ok' => true]);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    public static function eventChannelId(array $event): ?string
    {
        $channel = Arr::get($event, 'channel') ?? Arr::get($event, 'item.channel') ?? Arr::get($event, 'channel_id');

        return is_array($channel) ? Arr::get($channel, 'id') : $channel;
    }

    /**
     * A main-app event in a channel another workspace keeps for Verba (a shared channel): Verba skips it, tickets still
     * hear it, since tickets live in the main workspace only.
     *
     * @param  array<string, mixed>  $event
     */
    private function ticketsOnly(array $event): JsonResponse
    {
        if (Arr::get($event, 'type') === 'message') {
            dispatch(fn () => StoreTicketCommentFromSlackThread::run($event));
        }
        if (Arr::get($event, 'type') === 'reaction_added' && Arr::get($event, 'reaction') === config('services.slack.ticket_reaction') && Arr::get($event, 'item.type') === 'message') {
            $group = Group::firstOrFail();
            dispatch(fn () => $this->handle($group, Arr::get($event, 'item.channel'), Arr::get($event, 'item.ts'), Arr::get($event, 'user')));
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Another workspace (INI-540) brings its people, channels, public and private channel messages, reactions and pins.
     * Tickets, custom emoji and direct messages stay with the main workspace. A public channel it tells about for the first
     * time is created as its own before the usual handlers store anything in it.
     *
     * @param  array<string, mixed>  $event
     */
    private function fromOtherWorkspace(SlackWorkspace $workspace, array $event, ?string $channelId, ?string $botUserId): JsonResponse
    {
        $type = Arr::get($event, 'type');
        if ($channelId && ($type === 'channel_created' || ($type === 'message' && Arr::get($event, 'channel_type') === 'channel'))) {
            SlackChannel::firstOrCreate(['slack_id' => $channelId], ['group_id' => Group::firstOrFail()->id, 'slack_team_id' => $workspace->teamId, 'is_member' => true]);
        }

        match (true) {
            in_array($type, ['user_change', 'team_join'], true)          => Arr::get($event, 'user.team_id') === $workspace->teamId ? StoreSlackUser::dispatch((array) Arr::get($event, 'user', [])) : null,
            in_array($type, ['pin_added', 'pin_removed'], true)          => dispatch(fn () => StoreSlackPin::make()->fromEvent($event)),
            in_array($type, ['reaction_added', 'reaction_removed'], true) => StoreSlackMessageReaction::dispatch($event),
            in_array($type, UpdateSlackChannelFromEvent::EVENTS, true)    => $this->channelEvent($event, $botUserId, $workspace),
            $type === 'message'                                           => $this->otherWorkspaceMessage($event),
            default                                                       => null,
        };

        return response()->json(['ok' => true]);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function channelEvent(array $event, ?string $botUserId, SlackWorkspace $workspace): void
    {
        UpdateSlackChannelFromEvent::run($event, $botUserId, $workspace->teamId);
        if (in_array(Arr::get($event, 'type'), ['member_joined_channel', 'member_left_channel'], true)) {
            UpdateSlackChannelMember::dispatch($event);
        }
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function otherWorkspaceMessage(array $event): void
    {
        $channelType = Arr::get($event, 'channel_type');
        if (Arr::get($event, 'subtype') === 'channel_convert_to_private' || $channelType === 'group') {
            UpdateSlackChannelFromEvent::make()->markPrivate((string) Arr::get($event, 'channel'));
        }
        if ($channelType === 'group') {
            rescue(fn () => StoreSlackPrivateMessage::run($event), fn (Throwable $exception) => Log::warning('Slack private message not stored', ['channel' => Arr::get($event, 'channel'), 'ts' => Arr::get($event, 'ts'), 'error' => $exception::class]), false);
        } elseif ($channelType === 'channel' && Arr::get($event, 'subtype') !== 'channel_convert_to_private') {
            StoreSlackMessage::dispatch($event);
        }
    }
}
