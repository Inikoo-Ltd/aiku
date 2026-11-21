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
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class DisconnectSlackAccount
{
    use AsAction;

    public function handle(User $user, bool $callSlack = true): bool
    {
        $connection = SlackConnection::where('user_id', $user->id)->first();
        if (!$connection) {
            return true;
        }

        try {
            $token = $connection->token;
        } catch (DecryptException) {
            $token = null;
        }
        $revoked = $callSlack && $token !== null && $this->revokeAtSlack($token);

        $connection->forceFill(['token' => null, 'revoked_at' => now(), 'needs_reconnect' => false])->save();

        return $revoked;
    }

    public function revokeAtSlack(#[\SensitiveParameter] string $token): bool
    {
        try {
            $answer = Http::withToken($token)->asForm()->timeout(10)->post('https://slack.com/api/auth.revoke')->json();
        } catch (ConnectionException) {
            return false;
        }

        return (bool) data_get($answer, 'ok') || in_array(data_get($answer, 'error'), ['token_revoked', 'invalid_auth', 'account_inactive'], true);
    }

    public function asController(ActionRequest $request): RedirectResponse
    {
        $mayCallSlack = app()->environment(SlackWriteAccess::WRITE_ENVIRONMENTS);
        $revoked      = $this->handle($request->user(), $mayCallSlack);

        return StartSlackConnection::backToSettings(
            StartSlackConnection::NOTICE_KEY,
            match (true) {
                $revoked       => __('Your Slack account is disconnected'),
                !$mayCallSlack => __('Your Slack account is disconnected on this machine only; this machine never contacts Slack'),
                default        => __('Your Slack account is disconnected here, but Slack could not be reached to withdraw the permission. You can remove it in Slack under your account settings'),
            }
        );
    }
}
