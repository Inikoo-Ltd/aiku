<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 12 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Ticket\Concerns;

use App\Models\SysAdmin\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

trait WithSlack
{
    protected function slackSignatureIsValid(Request $request): bool
    {
        $secret    = config('services.slack.signing_secret');
        $timestamp = $request->header('X-Slack-Request-Timestamp');
        if (!$secret || !$timestamp || abs(time() - (int) $timestamp) > 300) {
            return false;
        }
        $expected = 'v0='.hash_hmac('sha256', 'v0:'.$timestamp.':'.$request->getContent(), $secret);

        return hash_equals($expected, (string) $request->header('X-Slack-Signature'));
    }

    protected function slackClient(): ?PendingRequest
    {
        $token = config('services.slack.notifications.bot_user_oauth_token');

        return $token ? Http::withToken($token)->baseUrl('https://slack.com/api') : null;
    }

    protected function slackUserToAikuUser(?string $slackUserId): ?User
    {
        if (!$slackUserId || !$client = $this->slackClient()) {
            return null;
        }

        if ($known = User::where('status', true)->where('slack_user_id', $slackUserId)->first()) {
            return $known;
        }

        $slackUser = $client->get('users.info', ['user' => $slackUserId])->json('user', []);
        $email     = strtolower((string) data_get($slackUser, 'profile.email'));
        $username  = strtolower((string) data_get($slackUser, 'name'));
        $realName  = trim((string) (data_get($slackUser, 'real_name') ?: data_get($slackUser, 'profile.real_name')));

        $user = User::query()
            ->where('status', true)
            ->where(function ($query) use ($email, $username, $realName) {
                $query->whereRaw('false');
                if ($email) {
                    $query->orWhereRaw('lower(email) = ?', [$email]);
                }
                if ($username) {
                    $query->orWhereRaw('lower(username) = ?', [$username]);
                }
                if ($realName) {
                    $query->orWhereRaw('lower(contact_name) = ?', [strtolower($realName)]);
                }
            })
            ->orderByRaw('lower(email) = ? desc', [$email])
            ->first();

        if ($user) {
            $user->update(['slack_user_id' => $slackUserId]);
        }

        return $user;
    }
}
