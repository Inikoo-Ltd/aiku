<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 08 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use App\Models\CRM\RetinaApiRequest;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\CRM\WebUser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Response as HttpStatus;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Records every customer API call so support and the customer can see what arrived and
 * what we answered. Credentials never reach the table: only a redacted, truncated JSON
 * body is kept and headers are not stored at all.
 */
class LogRetinaApiRequest
{
    public const int MAX_PAYLOAD_CHARS = 4000;

    /**
     * These carry other people's personal data: the customer's own clients, who never dealt with
     * us directly and whose record here would outlive the one they can ask us to delete. Enough
     * of the value survives to recognise what was sent without the value itself being kept.
     */
    protected const array PII_KEYS = [
        'name',
        'first_name',
        'last_name',
        'contact_name',
        'company_name',
        'email',
        'phone',
        'phone_number',
        'mobile',
        'address',
        'delivery_address',
        'billing_address',
        'address_line_1',
        'address_line_2',
        'postal_code',
        'locality',
        'city',
    ];

    protected const array REDACTED_KEYS = [
        'password',
        'password_confirmation',
        'token',
        'access_token',
        'api_token',
        'refresh_token',
        'secret',
        'client_secret',
        'authorization',
        'card',
        'card_number',
        'number',
        'cvv',
        'cvc',
        'pan',
        'expiry',
        'iban',
        'account_number',
        'sort_code',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('retina_api_request_start', microtime(true));

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            $this->record($request, $response);
        } catch (Throwable $e) {
            Log::warning('Failed to log retina API request', ['error' => $e->getMessage()]);
        }
    }

    protected function record(Request $request, Response $response): void
    {
        $user = $request->user();

        $customerId = match (true) {
            $user instanceof CustomerSalesChannel => $user->customer_id,
            $user instanceof WebUser              => $user->customer_id,
            default                               => null,
        };

        if (!$customerId) {
            return;
        }

        $status = $response->getStatusCode();

        if ($status === HttpStatus::HTTP_TOO_MANY_REQUESTS) {
            return;
        }

        $decoded = json_decode((string) $response->getContent(), true);

        RetinaApiRequest::create([
            'customer_id'              => $customerId,
            'customer_sales_channel_id' => $user instanceof CustomerSalesChannel ? $user->id : null,
            'web_user_id'              => $user instanceof WebUser ? $user->id : null,
            'personal_access_token_id' => method_exists($user, 'currentAccessToken') ? $user->currentAccessToken()?->id : null,
            'route_name'               => $request->route()?->getName(),
            'method'                   => $request->method(),
            'path'                     => mb_substr($request->path(), 0, 255),
            'route_parameters'         => $this->truncate($this->redact(array_merge($request->route()?->originalParameters() ?? [], $request->query()))),
            'payload'                  => $this->payload($request),
            'status'                   => $status,
            'message'                  => $this->violations($request, $status >= 400 ? $this->message($decoded) : null),
            'response_id'              => $status < 400 && is_array($decoded) ? $this->responseId($decoded) : null,
            'duration_ms'              => (int) ((microtime(true) - $request->attributes->get('retina_api_request_start', microtime(true))) * 1000),
            'ip'                       => $request->ip(),
            'user_agent'               => mb_substr((string) $request->header('User-Agent'), 0, 255),
        ]);
    }

    /**
     * @return array<array-key, mixed>|null
     */
    protected function payload(Request $request): ?array
    {
        if (!$request->isJson()) {
            return null;
        }

        $payload = json_decode((string) $request->getContent(), true);

        if (!is_array($payload)) {
            return null;
        }

        $payload = $this->redact($payload);

        return $this->truncate($payload);
    }

    /**
     * @param  array<array-key, mixed>  $payload
     * @return array<array-key, mixed>
     */
    protected function redact(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = in_array(mb_strtolower((string) $key), self::PII_KEYS, true)
                    ? $this->mask($value)
                    : $this->redact($value);

                continue;
            }

            if (in_array(mb_strtolower((string) $key), self::REDACTED_KEYS, true) || $this->looksLikeToken($value)) {
                $payload[$key] = '***';
                continue;
            }

            if (in_array(mb_strtolower((string) $key), self::PII_KEYS, true)) {
                $payload[$key] = $this->mask($value);
            }
        }

        return $payload;
    }

    /**
     * Keeps the first letter of each part so a support answer can say what shape of value
     * arrived - a**@g*** against the address the customer says they sent - while the value
     * itself is not retained. An address arrives as a nested object, so masking recurses.
     */
    protected function mask(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($item) => $this->mask($item), $value);
        }

        if (!is_string($value) || $value === '') {
            return $value === null || $value === '' ? $value : '***';
        }

        if (str_contains($value, '@')) {
            [$local, $domain] = explode('@', $value, 2);

            return mb_substr($local, 0, 1).'***@'.mb_substr($domain, 0, 1).'***';
        }

        return mb_substr($value, 0, 1).'***';
    }

    /**
     * A key blocklist cannot catch a token sent under a name nobody thought of, so anything
     * shaped like a Sanctum token is masked whatever it is called.
     */
    protected function looksLikeToken(mixed $value): bool
    {
        return is_string($value) && preg_match('/^\d+\|[A-Za-z0-9]{40}$/', $value) === 1;
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    protected function truncate(array $values): array
    {
        $encoded = (string) json_encode($values);

        if (mb_strlen($encoded) <= self::MAX_PAYLOAD_CHARS) {
            return $values;
        }

        return ['_truncated' => mb_substr($encoded, 0, self::MAX_PAYLOAD_CHARS)];
    }

    /**
     * Ownership violations recorded while enforcement is off travel alongside the response
     * message rather than replacing it, so a call that also failed for its own reason keeps it.
     */
    protected function violations(Request $request, ?string $message): ?string
    {
        $violations = $request->attributes->get('retina_api_ownership_violations', []);

        if (!$violations) {
            return $message;
        }

        return mb_substr(trim(implode('; ', $violations).($message ? ' | '.$message : '')), 0, 2000);
    }

    protected function message(mixed $decoded): ?string
    {
        if (!is_array($decoded)) {
            return null;
        }

        $message = Arr::get($decoded, 'message') ?? Arr::get($decoded, 'error');

        return $message ? mb_substr(is_string($message) ? $message : (string) json_encode($message), 0, 2000) : null;
    }

    /**
     * @param  array<array-key, mixed>  $decoded
     */
    protected function responseId(array $decoded): ?int
    {
        $id = Arr::get($decoded, 'data.id') ?? Arr::get($decoded, 'id');

        return is_numeric($id) ? (int) $id : null;
    }
}
