<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 14 Sep 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Ticket;

use App\Actions\Helpers\Ticket\Concerns\WithSlack;
use App\Models\SysAdmin\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;
use Throwable;

class SendTicketSlackDirectMessage
{
    use AsAction;
    use WithSlack;

    public int $jobTries = 5;
    public int $jobTimeout = 30;

    private const array PERMANENT_ERRORS = [
        'channel_not_found',
        'user_not_found',
        'user_disabled',
        'account_inactive',
        'cannot_dm_bot',
        'not_authed',
        'invalid_auth',
        'token_revoked',
        'missing_scope',
        'not_allowed_token_type',
        'is_archived',
        'msg_too_long',
        'no_text',
    ];

    /**
     * @return array<int, int>
     */
    public function getJobBackoff(): array
    {
        return [30, 120, 600, 1800];
    }

    public function handle(User $user, string $text): void
    {
        if (!$user->slack_user_id || !$client = $this->slackClient()) {
            return;
        }

        try {
            $response = $client->timeout(15)->post('chat.postMessage', [
                'channel' => $user->slack_user_id,
                'text'    => $text,
            ]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Slack unreachable sending ticket DM to user '.$user->id.': '.$exception->getMessage(), previous: $exception);
        }

        $error = $response->json('error');

        if ($response->successful() && $response->json('ok') === true) {
            return;
        }

        if ($response->status() === 429 || $response->serverError() || $error === 'ratelimited' || $error === 'service_unavailable' || $error === 'internal_error' || $error === 'fatal_error' || $error === 'request_timeout') {
            throw new RuntimeException('Slack temporarily refused ticket DM to user '.$user->id.': '.($error ?? 'HTTP '.$response->status()));
        }

        Log::warning('Slack ticket DM not delivered', [
            'user_id'       => $user->id,
            'slack_user_id' => $user->slack_user_id,
            'status'        => $response->status(),
            'error'         => $error ?? 'unknown',
            'permanent'     => in_array($error, self::PERMANENT_ERRORS, true),
        ]);
    }

    public function jobFailed(Throwable $exception): void
    {
        Log::error('Slack ticket DM failed after retries', ['message' => $exception->getMessage()]);
    }
}
