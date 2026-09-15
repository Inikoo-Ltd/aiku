<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Whatsapp\Concerns;

use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;

/**
 * The onboarding steps for a phone number all talk to Graph the same way and all report
 * back to the same modal, so they share the credential guard, the Graph error unwrapping
 * and the json envelope.
 */
trait WithWhatsappPhoneNumberResponse
{
    /**
     * @return array{ok: bool, message?: string, data?: array<string, mixed>, code?: int}
     */
    protected function notConfigured(): array
    {
        return [
            'ok'      => false,
            'message' => __('WhatsApp is not configured for this shop.'),
            'code'    => 422,
        ];
    }

    /**
     * Meta puts the part worth showing an admin in error.error_user_msg: "You have already
     * verified ownership of this phone number". Its error.message is the terse internal
     * label for the same failure ("Request code error"), so it only serves as a fallback.
     * Passing Meta's own wording through beats a generic failure, because the next step
     * depends on which one it was.
     *
     * @return array{ok: bool, message: string, code: int}
     */
    protected function graphFailure(Response $response, string $fallback): array
    {
        $error = Arr::get($response->json(), 'error', []);

        return [
            'ok'      => false,
            'message' => Arr::get($error, 'error_user_msg') ?: Arr::get($error, 'message') ?: $fallback,
            'code'    => 422,
        ];
    }

    /**
     * @param  array{ok: bool, message?: string, data?: array<string, mixed>, code?: int}  $result
     */
    public function jsonResponse(array $result): JsonResponse
    {
        if (!$result['ok']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], $result['code'] ?? 422);
        }

        return response()->json([
            'success' => true,
            ...Arr::only($result, ['data']),
        ]);
    }
}
