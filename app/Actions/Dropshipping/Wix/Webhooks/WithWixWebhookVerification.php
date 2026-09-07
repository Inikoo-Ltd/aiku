<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Wix\Webhooks;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Arr;

/**
 * Wix delivers webhooks as a signed JWT in the raw request body. The payload nests JSON inside
 * JSON: the JWT's `data` claim is a JSON string, and the event's own `data` is a JSON string
 * inside that.
 *
 * @see https://dev.wix.com/docs/build-apps/develop-your-app/api-integrations/events-and-webhooks/about-webhooks
 */
trait WithWixWebhookVerification
{
    /**
     * @return array{eventType?: string, instanceId?: string, data?: array, identity?: array}|null
     */
    public function verifyWixWebhook(string $rawBody): ?array
    {
        $publicKey = config('services.wix.public_key');

        if (blank($publicKey) || blank($rawBody)) {
            return null;
        }

        try {
            $decoded = JWT::decode(trim($rawBody), new Key($publicKey, 'RS256'));
        } catch (\Throwable) {
            return null;
        }

        $event = json_decode((string) ($decoded->data ?? ''), true);

        if (!is_array($event)) {
            return null;
        }

        return [
            'eventType'  => Arr::get($event, 'eventType'),
            'instanceId' => Arr::get($event, 'instanceId'),
            'data'       => $this->decodeNestedJson(Arr::get($event, 'data')),
            'identity'   => $this->decodeNestedJson(Arr::get($event, 'identity')),
        ];
    }

    private function decodeNestedJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || blank($value)) {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
