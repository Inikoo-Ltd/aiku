<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 21:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack\Write;

use App\Actions\Chat\Staff\Slack\SlackWorkspace;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class StartSlackConnection
{
    use AsAction;

    public const string STATE_KEY = 'slack_connect_state';

    public const string ERROR_KEY = 'slack_connect_error';

    public const string NOTICE_KEY = 'slack_connect_notice';

    private const int STATE_MINUTES = 10;

    public function authorize(ActionRequest $request): bool
    {
        return SlackWriteAccess::allows($request->user());
    }

    public static function isConfigured(?SlackWorkspace $workspace = null): bool
    {
        $workspace ??= SlackWorkspace::main();

        return $workspace->clientId && $workspace->clientSecret && $workspace->slackTeamId();
    }

    /**
     * The callback on the app's own domain (app.aiku.io), from the route's fixed domain, never the request host.
     * It must be listed verbatim under Redirect URLs in the Slack app; app.url is the bare domain and Slack refuses it.
     */
    public static function redirectUri(): string
    {
        return route('grp.chat.staff.slack.connect.callback');
    }

    public static function unavailableReason(User $user, ?SlackWorkspace $workspace = null): ?string
    {
        $workspace ??= SlackWorkspace::main();
        if (!app()->environment(SlackWriteAccess::WRITE_ENVIRONMENTS)) {
            return __('Connecting a Slack account is switched off on this machine');
        }
        if (!self::isConfigured($workspace)) {
            return __('Connecting a Slack account is not set up on this server');
        }
        if (!$workspace->isMain() && !$workspace->personOf($user)) {
            return __('You have no account in this Slack workspace, so it cannot be connected');
        }
        if (!$workspace->personOf($user)) {
            return __('Your aiku user is not linked to a Slack person with the same email, so it cannot be connected yet');
        }

        return null;
    }

    public static function backToSettings(string $key, string $message): RedirectResponse
    {
        session()->put($key, $message);

        return redirect()->route('grp.profile.edit', ['section' => 'slack']);
    }

    /**
     * Keeps a one-time state in the session and returns Slack's authorisation link for these user scopes.
     * $private carries the consent and small print versions the person agreed to, only for the private opt-in.
     *
     * @param  list<string>  $scopes
     * @param  array{consent_version: string, terms_version: string}|null  $private
     */
    public static function authorizeUrl(User $user, array $scopes, ?array $private = null, ?string $notice = null, ?SlackWorkspace $workspace = null): string
    {
        $workspace ??= SlackWorkspace::main();
        $state = Str::random(48);
        session()->put(self::STATE_KEY, [
            'value'      => $state,
            'user_id'    => $user->id,
            'expires_at' => now()->addMinutes(self::STATE_MINUTES)->timestamp,
            'private'    => $private,
            'notice'     => $notice,
            'team_id'    => $workspace->teamId,
        ]);

        return 'https://slack.com/oauth/v2/authorize?'.http_build_query([
            'client_id'    => $workspace->clientId,
            'user_scope'   => implode(',', $scopes),
            'redirect_uri' => self::redirectUri(),
            'state'        => $state,
            'team'         => $workspace->slackTeamId(),
        ]);
    }

    public function asController(ActionRequest $request): RedirectResponse
    {
        $teamId    = $request->query('team_id');
        $workspace = $teamId === null || (is_string($teamId) && $teamId !== '') ? SlackWorkspace::forTeam($teamId) : null;
        if (!$workspace) {
            return self::backToSettings(self::ERROR_KEY, __('Connecting a Slack account is not set up on this server'));
        }
        if ($reason = self::unavailableReason($request->user(), $workspace)) {
            return self::backToSettings(self::ERROR_KEY, $reason);
        }

        return redirect()->away(self::authorizeUrl($request->user(), SlackWriteAccess::USER_SCOPES, workspace: $workspace));
    }
}
