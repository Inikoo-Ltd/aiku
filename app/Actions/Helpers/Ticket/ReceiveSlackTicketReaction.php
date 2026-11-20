<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 12 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Ticket;

use App\Actions\Chat\Staff\Slack\StoreSlackMessage;
use App\Actions\Chat\Staff\Slack\StoreSlackMessageReaction;
use App\Actions\Chat\Staff\Slack\UpdateSlackChannelFromEvent;
use App\Actions\Chat\Staff\Slack\StoreSlackUser;
use App\Actions\Helpers\Ticket\Concerns\WithSlack;
use App\Models\Helpers\Ticket;
use App\Models\SysAdmin\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

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
        abort_unless($this->slackSignatureIsValid($request), 401);

        if ($request->input('type') === 'url_verification') {
            return response()->json(['challenge' => $request->input('challenge')]);
        }

        $event = $request->input('event', []);
        if (in_array(Arr::get($event, 'type'), ['user_change', 'team_join'], true)) {
            StoreSlackUser::dispatch((array) Arr::get($event, 'user', []));

            return response()->json(['ok' => true]);
        }
        if (in_array(Arr::get($event, 'type'), UpdateSlackChannelFromEvent::EVENTS, true)) {
            UpdateSlackChannelFromEvent::run($event, $request->input('authorizations.0.user_id'));

            return response()->json(['ok' => true]);
        }
        if (Arr::get($event, 'type') === 'message') {
            if (Arr::get($event, 'subtype') === 'channel_convert_to_private' || Arr::get($event, 'channel_type') === 'group') {
                UpdateSlackChannelFromEvent::make()->markPrivate((string) Arr::get($event, 'channel'));
            }
            dispatch(fn () => StoreTicketCommentFromSlackThread::run($event));
            if (Arr::get($event, 'subtype') !== 'channel_convert_to_private') {
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
}
