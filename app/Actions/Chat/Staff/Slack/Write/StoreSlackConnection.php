<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 21:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack\Write;

use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackConnection;
use App\Models\SysAdmin\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreSlackConnection
{
    use AsAction;

    public function authorize(ActionRequest $request): bool
    {
        return SlackWriteAccess::allows($request->user());
    }

    public function asController(ActionRequest $request): RedirectResponse
    {
        $state = $request->session()->pull(StartSlackConnection::STATE_KEY);
        $error = $this->handle($request->user(), $state, $request->query('state'), $request->query('code'), $request->query('error'));

        return StartSlackConnection::backToSettings(
            $error ? StartSlackConnection::ERROR_KEY : StartSlackConnection::NOTICE_KEY,
            $error ?? __('Your Slack account is connected')
        );
    }

    /**
     * @param  array{value: string, user_id: int, expires_at: int}|null  $state
     */
    public function handle(User $user, ?array $state, mixed $returnedState, mixed $code, mixed $slackError): ?string
    {
        if (!is_array($state) || !is_string($returnedState) || !hash_equals((string) ($state['value'] ?? ''), $returnedState)
            || ($state['user_id'] ?? null) !== $user->id || ($state['expires_at'] ?? 0) < now()->timestamp) {
            return __('The connection request was not valid or had expired. Nothing was connected; please try again');
        }
        if ($slackError || !is_string($code) || $code === '') {
            return __('You did not give permission in Slack, so nothing was connected');
        }
        $slackPerson = SlackChannel::linkedSlackPerson($user);
        if ($reason = StartSlackConnection::unavailableReason($user)) {
            return $reason;
        }

        try {
            $answer = Http::asForm()->timeout(8)
                ->withBasicAuth(config('services.slack.client_id'), config('services.slack.client_secret'))
                ->post('https://slack.com/api/oauth.v2.access', ['code' => $code, 'redirect_uri' => StartSlackConnection::redirectUri()])
                ->json();
        } catch (ConnectionException) {
            $answer = null;
        }
        $token = data_get($answer, 'authed_user.access_token');
        if (!data_get($answer, 'ok') || !is_string($token) || $token === '' || data_get($answer, 'authed_user.token_type') !== 'user') {
            return __('Slack did not confirm the connection, so nothing was connected. Please try again');
        }

        $granted = array_map('trim', explode(',', (string) data_get($answer, 'authed_user.scope')));
        $refusal = match (true) {
            data_get($answer, 'team.id') !== config('services.slack.team_id')                                       => __('That Slack account belongs to another workspace, so nothing was connected'),
            data_get($answer, 'authed_user.id') !== $slackPerson->slack_id                                          => __('That Slack account is not the one linked to your aiku user, so nothing was connected. Sign in to Slack as yourself and try again'),
            data_get($answer, 'authed_user.refresh_token') !== null || data_get($answer, 'authed_user.expires_in') !== null => __('Slack returned a kind of permission aiku cannot keep, so nothing was connected'),
            array_diff(SlackWriteAccess::USER_SCOPES, $granted) !== [] => __('Slack did not give every permission aiku needs, so nothing was connected. Please try again and allow them all'),
            default                                                                                                 => null,
        };
        if ($refusal) {
            if (!SlackConnection::where('slack_user_id', data_get($answer, 'authed_user.id'))->live()->get()->contains(fn (SlackConnection $kept) => $kept->missingScopes() === [])) {
                DisconnectSlackAccount::make()->revokeAtSlack($token);
            }

            return $refusal;
        }

        SlackConnection::updateOrCreate(['user_id' => $user->id], [
            'slack_user_id'   => $slackPerson->slack_id,
            'team_id'         => data_get($answer, 'team.id'),
            'token'           => $token,
            'scopes'          => implode(',', $granted),
            'connected_at'    => now(),
            'revoked_at'      => null,
            'needs_reconnect' => false,
        ]);

        return null;
    }
}
