<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 21:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack\Write;

use App\Actions\Chat\Staff\Slack\SlackWorkspace;
use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackConnection;
use App\Models\Chat\SlackUser;
use App\Models\SysAdmin\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class SlackWriteClient
{
    public const array RECONNECT_ERRORS = ['token_revoked', 'invalid_auth', 'account_inactive', 'missing_scope'];

    private const int TIMEOUT_SECONDS = 8;

    private function __construct(private readonly ?SlackConnection $connection, #[\SensitiveParameter] private readonly string $token, private readonly ?SlackUser $standInFor = null)
    {
    }

    /**
     * For a person who has not connected their Slack, the Desk bot posts in their place under their name and photo
     * (Slack labels it APP). It can post, edit and delete what it posted, never react or touch the person's own messages.
     */
    public static function standInFor(User $user, ?SlackWorkspace $workspace = null): ?self
    {
        $workspace ??= SlackWorkspace::main();
        $token  = $workspace->botToken;
        $person = $workspace->isMain() ? SlackChannel::linkedSlackPerson($user) : self::personIn($user, $workspace);
        if ($token === '' || !$person || !SlackWriteAccess::allows($user) || !app()->environment(SlackWriteAccess::WRITE_ENVIRONMENTS)) {
            return null;
        }

        return new self(null, $token, $person);
    }

    /**
     * The person's own account in that workspace when they have one, so what they post there is theirs; else their main one.
     */
    private static function personIn(User $user, SlackWorkspace $workspace): ?SlackUser
    {
        $linkedIds = SlackChannel::linkedSlackIds($user);

        return SlackUser::whereIn('slack_id', $linkedIds)->where('data->team_id', $workspace->teamId)->first() ?? SlackChannel::linkedSlackPerson($user);
    }

    public function isStandIn(): bool
    {
        return $this->standInFor !== null;
    }

    public static function for(User $user): ?self
    {
        if (!SlackWriteAccess::allows($user) || !app()->environment(SlackWriteAccess::WRITE_ENVIRONMENTS)) {
            return null;
        }
        $connection = SlackConnection::where('user_id', $user->id)->live()->first();

        if (!$connection || $connection->missingScopes() !== []) {
            return null;
        }

        try {
            return new self($connection, $connection->token);
        } catch (DecryptException) {
            $connection->forceFill(['needs_reconnect' => true])->save();

            return null;
        }
    }

    public function slackUserId(): string
    {
        return $this->standInFor?->slack_id ?? $this->connection->slack_user_id;
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     *
     * @throws SlackWriteException
     */
    public function call(string $method, array $parameters): array
    {
        if ($this->standInFor && $method === 'chat.postMessage') {
            $parameters += [
                'username' => $this->standInFor->real_name ?: $this->standInFor->display_name ?: $this->standInFor->name,
                'icon_url' => $this->standInFor->avatar_url,
            ];
        }
        $form = array_map(
            fn ($value) => is_bool($value) ? ($value ? 'true' : 'false') : (is_array($value) ? json_encode($value) : $value),
            array_filter($parameters, fn ($value) => $value !== null)
        );

        try {
            $response = Http::withToken($this->token)->asForm()->timeout(self::TIMEOUT_SECONDS)->post('https://slack.com/api/'.$method, $form);
        } catch (ConnectionException) {
            throw new SlackWriteException('unreachable', $method);
        }

        if ($response->status() === 429) {
            throw new SlackWriteException('ratelimited', $method);
        }
        if (!$response->json('ok')) {
            $error = (string) $response->json('error', 'http_'.$response->status());
            if ($this->connection && in_array($error, self::RECONNECT_ERRORS, true)) {
                $this->connection->update(['needs_reconnect' => true]);
            }
            throw new SlackWriteException($error, $method);
        }

        return $response->json();
    }
}
