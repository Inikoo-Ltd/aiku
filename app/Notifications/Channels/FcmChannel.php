<?php

/*
 * Author Louis Perez
 * Created on 18-11-2026-08h-47m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Notifications\Channels;

use App\Exceptions\FcmAccessTokenRefused;
use App\Exceptions\FcmMessageRejected;
use App\Models\Notifications\FcmToken;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Firebase Cloud Messaging (HTTP v1) to every device the user signed in to the mobile app on.
 * Without credentials nothing is pushed and the database notification still stands.
 */
class FcmChannel
{
    public const string SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    public const string DEFAULT_TOKEN_URI = 'https://oauth2.googleapis.com/token';

    public const array DEAD_TOKEN_ERRORS = ['UNREGISTERED', 'INVALID_ARGUMENT'];

    public function send(object $notifiable, Notification $notification): void
    {
        if (!method_exists($notification, 'toFcm') || !method_exists($notifiable, 'fcmTokens')) {
            return;
        }

        $credentials = self::credentials();
        if ($credentials === null) {
            return;
        }

        $tokens = $notifiable->fcmTokens()->get();
        if ($tokens->isEmpty()) {
            return;
        }

        $message  = $notification->toFcm($notifiable);
        $endpoint = 'https://fcm.googleapis.com/v1/projects/'.self::projectId($credentials).'/messages:send';
        $retry    = null;

        /** @var FcmToken $token */
        foreach ($tokens as $token) {
            try {
                $response = $this->post($credentials, $endpoint, array_merge($message, ['token' => $token->fcm_token]));
            } catch (FcmAccessTokenRefused $exception) {
                if ($exception->isTemporary) {
                    throw $exception;
                }

                report($exception);
                break;
            }

            if ($response->successful()) {
                continue;
            }

            $errorCode = self::errorCode($response);

            if (in_array($errorCode, self::DEAD_TOKEN_ERRORS, true)) {
                $token->delete();
            } elseif ($response->status() === 429 || $response->serverError()) {
                $retry ??= $response;
            } else {
                report(new FcmMessageRejected($response->status(), $errorCode, $token->id));
            }
        }

        $retry?->throw();
    }

    /**
     * @param array<string, mixed> $credentials
     * @param array<string, mixed> $message
     */
    private function post(array $credentials, string $endpoint, array $message): Response
    {
        $response = Http::withToken(self::accessToken($credentials))->acceptJson()->post($endpoint, ['message' => $message]);

        if ($response->status() === 401) {
            $response = Http::withToken(self::accessToken($credentials, refresh: true))->acceptJson()->post($endpoint, ['message' => $message]);
        }

        return $response;
    }

    private static function errorCode(Response $response): ?string
    {
        foreach ((array) $response->json('error.details', []) as $detail) {
            if (is_array($detail) && isset($detail['errorCode'])) {
                return $detail['errorCode'];
            }
        }

        return $response->json('error.status');
    }

    /**
     * FIREBASE_CREDENTIALS is the service account JSON itself, the JSON base64-encoded, or a path
     * to the JSON file.
     *
     * @return array<string, mixed>|null
     */
    public static function credentials(): ?array
    {
        $value = trim((string) config('services.fcm.credentials'));
        if ($value === '') {
            return null;
        }

        $json = str_starts_with($value, '{') ? $value : null;

        if ($json === null) {
            $decoded = base64_decode($value, true);
            $json    = $decoded !== false && json_decode($decoded) !== null ? $decoded : null;
        }

        if ($json === null) {
            $json = rescue(fn () => is_file($value) ? file_get_contents($value) : null, report: false);
        }

        $credentials = is_string($json) ? json_decode($json, true) : null;

        return is_array($credentials) && !empty($credentials['client_email']) && !empty($credentials['private_key']) ? $credentials : null;
    }

    /**
     * @param array<string, mixed> $credentials
     */
    private static function projectId(array $credentials): string
    {
        $projectId = config('services.fcm.project_id') ?: ($credentials['project_id'] ?? null);

        if (!$projectId) {
            throw new RuntimeException('FIREBASE_PROJECT_ID is not set and the Firebase credentials carry no project_id.');
        }

        return $projectId;
    }

    /**
     * @param array<string, mixed> $credentials
     */
    private static function accessToken(array $credentials, bool $refresh = false): string
    {
        $cacheKey = 'fcm-access-token:'.md5((string) $credentials['client_email']);

        if ($refresh) {
            Cache::forget($cacheKey);
        } elseif ($token = Cache::get($cacheKey)) {
            return $token;
        }

        return Cache::lock($cacheKey.':lock', 30)->block(15, function () use ($cacheKey, $credentials, $refresh) {
            if (!$refresh && $token = Cache::get($cacheKey)) {
                return $token;
            }

            try {
                $response = Http::asForm()->post($credentials['token_uri'] ?? self::DEFAULT_TOKEN_URI, [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion'  => self::assertion($credentials),
                ]);
            } catch (ConnectionException) {
                throw new FcmAccessTokenRefused(null, 'connection_failed', true);
            }

            $token = (string) $response->json('access_token');

            if ($response->failed() || $token === '') {
                $error = $response->json('error');

                throw new FcmAccessTokenRefused($response->status(), is_string($error) ? $error : null, $response->status() === 429 || $response->serverError());
            }

            Cache::put($cacheKey, $token, max(60, (int) $response->json('expires_in', 3600) - 300));

            return $token;
        });
    }

    /**
     * @param array<string, mixed> $credentials
     */
    private static function assertion(array $credentials): string
    {
        $issuedAt = time();
        $segments = [
            self::base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
            self::base64Url(json_encode([
                'iss'   => $credentials['client_email'],
                'scope' => self::SCOPE,
                'aud'   => $credentials['token_uri'] ?? self::DEFAULT_TOKEN_URI,
                'iat'   => $issuedAt,
                'exp'   => $issuedAt + 3600,
            ])),
        ];

        $privateKey = openssl_pkey_get_private((string) $credentials['private_key']);

        if ($privateKey === false || !openssl_sign(implode('.', $segments), $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new FcmAccessTokenRefused(null, 'invalid_private_key', false);
        }

        $segments[] = self::base64Url($signature);

        return implode('.', $segments);
    }

    private static function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
