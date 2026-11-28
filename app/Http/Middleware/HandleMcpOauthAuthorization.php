<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Nov 2026 12:25:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use App\Enums\SysAdmin\McpOauthAttempt\McpOauthAttemptOutcomeEnum;
use App\Models\SysAdmin\McpOauthAttempt;
use App\Models\SysAdmin\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Passport\Client;
use PragmaRX\Google2FALaravel\Support\Authenticator;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * The page where staff connect an AI assistant (ChatGPT, Claude) to Aiku.
 *
 * Passport keeps one consent token in the session, so a second load of the page (a browser
 * extension, a prefetch, a second tab) made the open page's Authorize button fail with a 403.
 * Every consent page shown in this session stays valid until it is used. Users with 2FA must
 * enter their code before they can connect, every attempt is recorded for the AI analytics
 * page, and failures show a page that says what to do instead of raw JSON.
 */
class HandleMcpOauthAuthorization
{
    private const string CONSENTS_SESSION_KEY = 'mcp_oauth_consents';

    private const int KEPT_CONSENTS = 5;

    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->is('oauth/authorize')) {
            return $next($request);
        }

        if ($request->user('web') && !$this->passedTwoFactor($request)) {
            if ($request->isMethod('GET')) {
                $request->session()->put('url.intended', $request->fullUrl());
            }
            $this->record($request, McpOauthAttemptOutcomeEnum::TWO_FA_REQUIRED, 302);

            return redirect()->route('grp.login.show2fa');
        }

        if (!$request->isMethod('GET')) {
            $this->restoreConsent($request);
        }

        $response = $next($request);

        if ($request->isMethod('GET') && $response->getStatusCode() === 200) {
            $this->keepConsent($request);
        }

        $this->record($request, $this->outcome($request, $response), $response->getStatusCode(), $this->errorMessage($response));

        if ($response->getStatusCode() >= 400 && !$request->expectsJson()) {
            return response()->view('mcp.authorize-error', [
                'reason' => $request->isMethod('GET') ? 'incomplete_link' : 'expired',
            ], $response->getStatusCode());
        }

        return $response;
    }

    private function passedTwoFactor(Request $request): bool
    {
        if (app()->isLocal()) {
            return true;
        }

        $request->query->remove(config('google2fa.otp_input'));
        $request->request->remove(config('google2fa.otp_input'));

        return (new Authenticator($request))->isAuthenticated();
    }

    private function keepConsent(Request $request): void
    {
        $authToken = $request->session()->get('authToken');
        $authRequest = $request->session()->get('authRequest');
        if (!$authToken || !$authRequest) {
            return;
        }

        $consents = $request->session()->get(self::CONSENTS_SESSION_KEY, []);
        $consents[$authToken] = $authRequest;

        $request->session()->put(self::CONSENTS_SESSION_KEY, array_slice($consents, -self::KEPT_CONSENTS, null, true));
    }

    private function restoreConsent(Request $request): void
    {
        $authToken = (string) $request->input('auth_token');
        $consents = $request->session()->get(self::CONSENTS_SESSION_KEY, []);

        if ($authToken === '' || !array_key_exists($authToken, $consents)) {
            return;
        }

        $request->session()->put('authToken', $authToken);
        $request->session()->put('authRequest', $consents[$authToken]);
        $request->session()->put(self::CONSENTS_SESSION_KEY, Arr::except($consents, $authToken));
    }

    private function outcome(Request $request, Response $response): McpOauthAttemptOutcomeEnum
    {
        $status = $response->getStatusCode();
        /** @var User|null $user */
        $user = $request->user('web');

        return match (true) {
            $status >= 400, $this->redirectError($response) !== null => McpOauthAttemptOutcomeEnum::FAILED,
            !$user => McpOauthAttemptOutcomeEnum::LOGIN_REQUIRED,
            $request->isMethod('DELETE') => McpOauthAttemptOutcomeEnum::CANCELLED,
            $status === 200 && !$user->can_use_mcp => McpOauthAttemptOutcomeEnum::NO_AI_ACCESS,
            $status === 200 => McpOauthAttemptOutcomeEnum::CONSENT_SHOWN,
            default => McpOauthAttemptOutcomeEnum::CONNECTED,
        };
    }

    private function redirectError(Response $response): ?string
    {
        if (!$response->isRedirection()) {
            return null;
        }

        parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY), $query);

        return isset($query['error']) ? trim($query['error'].' '.($query['error_description'] ?? '')) : null;
    }

    private function errorMessage(Response $response): ?string
    {
        if ($response->getStatusCode() < 400) {
            return $this->redirectError($response);
        }

        $body = json_decode((string) $response->getContent(), true);
        if (is_array($body) && isset($body['error'])) {
            return mb_substr(trim(Arr::get($body, 'error_description', $body['error']).' '.Arr::get($body, 'hint', '')), 0, 2000);
        }

        return $response->getStatusCode() === 403 ? 'The connect page was out of date' : null;
    }

    private function record(Request $request, McpOauthAttemptOutcomeEnum $outcome, int $statusCode, ?string $error = null): void
    {
        $clientId = (string) $request->input('client_id');
        if (!Str::isUuid($clientId)) {
            return;
        }

        try {
            /** @var User|null $user */
            $user = $request->user('web');

            McpOauthAttempt::create([
                'group_id'    => $user?->group_id,
                'user_id'     => $user?->id,
                'client_id'   => $clientId,
                'client_name' => Client::query()->whereKey($clientId)->value('name'),
                'outcome'     => $outcome,
                'status_code' => $statusCode,
                'error'       => $error,
                'ip'          => $request->ip(),
                'user_agent'  => mb_substr((string) $request->userAgent(), 0, 1000),
            ]);
        } catch (Throwable $e) {
            Log::warning('Failed to record MCP OAuth attempt', ['error' => $e->getMessage()]);
        }
    }
}
