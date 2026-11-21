<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 21:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack\Write;

use App\Models\Chat\SlackConnection;
use App\Models\SysAdmin\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class SlackWriteClient
{
    public const array RECONNECT_ERRORS = ['token_revoked', 'invalid_auth', 'account_inactive', 'missing_scope'];

    private const int TIMEOUT_SECONDS = 8;

    private function __construct(private readonly SlackConnection $connection, #[\SensitiveParameter] private readonly string $token)
    {
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
        return $this->connection->slack_user_id;
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     *
     * @throws SlackWriteException
     */
    public function call(string $method, array $parameters): array
    {
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
            if (in_array($error, self::RECONNECT_ERRORS, true)) {
                $this->connection->update(['needs_reconnect' => true]);
            }
            throw new SlackWriteException($error, $method);
        }

        return $response->json();
    }
}
