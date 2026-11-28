<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 20 Nov 2026 16:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack;

use App\Events\SlackChannelMessageChanged;
use App\Events\SlackDirectMessageChanged;
use App\Models\Chat\SlackMessage;
use App\Models\Chat\SlackMessageReaction;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Reactions are kept only on messages aiku already holds from a channel still public; Slack is never asked for a message.
 */
class StoreSlackMessageReaction
{
    use AsAction;

    public string $commandSignature = 'slack:fill-message-reactions';

    public string $commandDescription = 'Keep the reactions of the stored Slack messages, read from the history Slack gave with them';

    /**
     * A reaction_added or reaction_removed event of the Slack Events API; Slack may send the same one twice.
     *
     * @param  array<string, mixed>  $event
     */
    public function handle(array $event): void
    {
        $this->apply($event, direct: false);
    }

    /**
     * A reaction in a Slack DM or group DM (INI-593): only on a message Verba holds of a DM still kept, told to its members
     * by ids only. Reactions carry no words, so they are kept whoever made them.
     *
     * @param  array<string, mixed>  $event
     */
    public function inDirect(array $event): void
    {
        $this->apply($event, direct: true);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function apply(array $event, bool $direct): void
    {
        $slackUserId = Arr::get($event, 'user');
        $name        = Arr::get($event, 'reaction');
        if (Arr::get($event, 'item.type') !== 'message' || !$slackUserId || !$name) {
            return;
        }

        $message = SlackMessage::query()
            ->where('ts', (string) Arr::get($event, 'item.ts'))
            ->whereHas('channel', fn ($channel) => ($direct ? $channel->keptDirect() : $channel->kept())->where('slack_id', Arr::get($event, 'item.channel')))
            ->first();
        if (!$message) {
            return;
        }

        $key = ['slack_message_id' => $message->id, 'slack_user_id' => $slackUserId, 'name' => $name];
        if (Arr::get($event, 'type') === 'reaction_removed') {
            SlackMessageReaction::where($key)->delete();
            if ($message->data !== null) {
                $message->update(['data' => ['reactions' => $this->withoutReaction((array) Arr::get($message->data, 'reactions', []), $name, $slackUserId)] + (array) $message->data]);
            }
            $this->announce($message, $direct);

            return;
        }

        $eventTs = Arr::get($event, 'event_ts');
        SlackMessageReaction::firstOrCreate($key, ['reacted_at' => $eventTs ? Carbon::createFromTimestamp((float) $eventTs) : now()]);
        $this->announce($message, $direct);
    }

    private function announce(SlackMessage $message, bool $direct): void
    {
        if ($direct) {
            SlackDirectMessageChanged::announce($message->channel, $message, 'changed');

            return;
        }
        rescue(fn () => SlackChannelMessageChanged::dispatch($message->channel, $message, 'reaction'));
    }

    /**
     * @param  array<int, array<string, mixed>>  $reactions
     * @return array<int, array<string, mixed>>
     */
    private function withoutReaction(array $reactions, string $name, string $slackUserId): array
    {
        return collect($reactions)
            ->map(function (array $reaction) use ($name, $slackUserId) {
                if (Arr::get($reaction, 'name') !== $name) {
                    return $reaction;
                }
                $users = array_values(array_diff((array) Arr::get($reaction, 'users', []), [$slackUserId]));

                return ['users' => $users, 'count' => count($users)] + $reaction;
            })
            ->filter(fn (array $reaction) => Arr::get($reaction, 'name') !== $name || Arr::get($reaction, 'users'))
            ->values()
            ->all();
    }

    /**
     * Adds the reactions in the message as Slack gave it; never removes any, because one added since by an event is not in it.
     */
    public function fromStoredMessage(SlackMessage $message): int
    {
        $rows = [];
        foreach ((array) Arr::get($message->data, 'reactions', []) as $reaction) {
            if (!Arr::get($reaction, 'name')) {
                continue;
            }
            foreach ((array) Arr::get($reaction, 'users', []) as $slackUserId) {
                $rows[] = [
                    'slack_message_id' => $message->id,
                    'slack_user_id'    => $slackUserId,
                    'name'             => Arr::get($reaction, 'name'),
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ];
            }
        }

        return $rows ? SlackMessageReaction::insertOrIgnore($rows) : 0;
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $added = 0;
        SlackMessage::query()
            ->whereRaw("jsonb_typeof(data->'reactions') = 'array'")
            ->whereHas('channel', fn ($channel) => $channel->where('is_public', true)->where('is_ignored', false))
            ->chunkById(500, function ($messages) use (&$added) {
                foreach ($messages as $message) {
                    $added += $this->fromStoredMessage($message);
                }
            });
        $command->info("Reactions added: $added");

        return 0;
    }
}
