<?php

namespace App\Actions\Helpers\AI\Traits;

use App\Exceptions\AICreditException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use OpenAI\Exceptions\ErrorException;
use Throwable;

trait WithAICreditErrorHandler
{
    /**
     * Error codes and types ChatGPT returns when the account cannot pay for the call.
     */
    private const array AI_CREDIT_ERROR_CODES = [
        'insufficient_quota',
        'insufficient_credit',
        'insufficient_balance',
        'billing_hard_limit_reached',
        'billing_not_active',
        'account_deactivated',
        'quota_exceeded',
    ];

    private const array AI_CREDIT_ERROR_NEEDLES = [
        'insufficient_quota',
        'insufficient credit',
        'insufficient balance',
        'insufficient funds',
        'exceeded your current quota',
        'billing_hard_limit_reached',
        'billing hard limit',
        'check your plan and billing',
    ];

    public function isAICreditThrowable(Throwable $throwable): bool
    {
        if ($throwable instanceof AICreditException) {
            return true;
        }

        if ($throwable instanceof ErrorException
            && $this->matchesAICreditCode([(string) $throwable->getErrorCode(), (string) $throwable->getErrorType()])
        ) {
            return true;
        }

        if ($throwable instanceof RequestException && $this->isAICreditResponse($throwable->response)) {
            return true;
        }

        return $this->hasAICreditNeedle($throwable->getMessage());
    }

    public function isAICreditResponse(Response $response): bool
    {
        if ($response->successful()) {
            return false;
        }

        if ($response->status() === 402) {
            return true;
        }

        $payload = $response->json();

        if (is_array($payload) && $this->matchesAICreditCode([
            (string) Arr::get($payload, 'error.code'),
            (string) Arr::get($payload, 'error.type'),
        ])) {
            return true;
        }

        return $this->hasAICreditNeedle((string) $response->body());
    }

    /**
     * Backoffice operators are told to chase the credit, customers only get pointed at support.
     */
    public function aiCreditErrorMessage(?string $audience = null): string
    {
        $audience ??= $this->aiCreditErrorAudience();

        if ($audience === 'retina') {
            return __('The AI service is not available right now, please contact our IT Support.');
        }

        return __('The AI service has run out of credit, please contact IT Leader to fix the issue.');
    }

    /**
     * @throws \App\Exceptions\AICreditException
     */
    public function throwAICreditError(?string $audience = null): never
    {
        throw new AICreditException($this->aiCreditErrorMessage($audience));
    }

    /**
     * Turn a failed ChatGPT call into a user facing credit message, leaving every other failure untouched.
     *
     * @throws \App\Exceptions\AICreditException
     */
    public function rethrowAICreditThrowable(Throwable $throwable, ?string $audience = null): void
    {
        if (!$this->isAICreditThrowable($throwable)) {
            return;
        }

        Log::error('ChatGPT credit exhausted: '.$throwable->getMessage());

        $this->throwAICreditError($audience);
    }

    /**
     * @throws \App\Exceptions\AICreditException
     */
    public function guardAICreditResponse(Response $response, ?string $audience = null): void
    {
        if (!$this->isAICreditResponse($response)) {
            return;
        }

        Log::error('ChatGPT credit exhausted: '.$response->body());

        $this->throwAICreditError($audience);
    }

    protected function aiCreditErrorAudience(): string
    {
        $routeName = Route::currentRouteName();

        if (is_string($routeName) && (str_starts_with($routeName, 'retina.') || str_starts_with($routeName, 'iris.'))) {
            return 'retina';
        }

        return 'grp';
    }

    /**
     * @param array<int, string> $identifiers
     */
    private function matchesAICreditCode(array $identifiers): bool
    {
        return array_intersect($identifiers, self::AI_CREDIT_ERROR_CODES) !== [];
    }

    private function hasAICreditNeedle(string $haystack): bool
    {
        $haystack = strtolower($haystack);

        foreach (self::AI_CREDIT_ERROR_NEEDLES as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
