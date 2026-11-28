<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 21:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack\Write;

use App\Actions\Chat\Staff\Slack\ResolveSlackEmoji;
use App\Actions\Chat\Staff\Slack\StoreSlackMessageReaction;
use App\Models\Chat\SlackChannel;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Adds or removes the person's reaction in Slack with their own token, then keeps it at once through the code the
 * reaction events use, so the event Slack sends back finds it already there.
 */
class ToggleSlackMessageReaction
{
    use AsAction;
    use WithSlackMessageWrite;

    public const string NAME_PATTERN = "/^[a-z0-9_+'-]+(::skin-tone-[2-6])?$/";

    protected function writesDirect(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ts'    => ['required', 'string', 'regex:/^\d{1,12}\.\d{1,8}$/'],
            'name'  => ['required_without:emoji', 'nullable', 'string', 'max:100', 'regex:'.self::NAME_PATTERN],
            'emoji' => ['required_without:name', 'nullable', 'string', 'max:32'],
            'add'   => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function handle(User $user, SlackChannel $slackChannel, string $ts, string $name, bool $add): ?array
    {
        $this->ensureMayWrite($user, $slackChannel);
        $message = $this->liveMessage($slackChannel, $ts);
        $client  = $this->clientFor($user, channel: $slackChannel);

        $this->callSlack(
            $client,
            $add ? 'reactions.add' : 'reactions.remove',
            ['channel' => $slackChannel->slack_id, 'timestamp' => $message->ts, 'name' => $name],
            [$add ? 'already_reacted' : 'no_reaction']
        );

        $event = [
            'type'     => $add ? 'reaction_added' : 'reaction_removed',
            'user'     => $client->slackUserId(),
            'reaction' => $name,
            'item'     => ['type' => 'message', 'channel' => $slackChannel->slack_id, 'ts' => $message->ts],
            'event_ts' => sprintf('%.6f', microtime(true)),
        ];
        if ($slackChannel->direct_type) {
            StoreSlackMessageReaction::make()->inDirect($event);
        } else {
            StoreSlackMessageReaction::make()->handle($event);
        }

        return $this->row($slackChannel, $message->ts, $user);
    }

    /**
     * The picker gives a character; Slack wants its canonical name, which ResolveSlackEmoji::STANDARD lists first.
     */
    public static function nameOf(string $emoji): ?string
    {
        $strip = fn (string $value) => str_replace("\u{FE0F}", '', $value);
        foreach (ResolveSlackEmoji::STANDARD as $name => $unicode) {
            if ($strip($unicode) === $strip($emoji)) {
                return $name;
            }
        }

        return null;
    }

    public function asController(SlackChannel $slackChannel, ActionRequest $request): array
    {
        $name = $request->validated('name') ?? self::nameOf((string) $request->validated('emoji'));
        if (!$name) {
            $this->refuse(__('Slack does not know this emoji'));
        }

        return ['message' => $this->handle($request->user(), $slackChannel, $request->validated('ts'), $name, (bool) $request->validated('add'))];
    }
}
