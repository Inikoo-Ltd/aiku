<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 13 Jul 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Services;

use Exception;
use Illuminate\Http\Client\StrayRequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Ssr\HttpGateway;
use Inertia\Ssr\Response;

/**
 * Inertia's HttpGateway swallows every SSR dispatch exception and silently falls
 * back to client-side rendering, which made production SSR failures invisible
 * (e.g. awartisan.de/summer served CSR for weeks with CLS 0.43). This gateway
 * reports the failure with enough context to find the page before returning null.
 */
class ReportingSsrGateway extends HttpGateway
{
    public function dispatch(array $page): ?Response
    {
        if (!$this->shouldDispatch()) {
            return null;
        }

        try {
            $response = Http::post($this->getUrl('/render'), $page)->throw()->json();
        } catch (Exception $e) {
            if ($e instanceof StrayRequestException) {
                throw $e;
            }

            Log::warning('Inertia SSR dispatch failed, falling back to CSR', [
                'url'       => Arr::get($page, 'url'),
                'component' => Arr::get($page, 'component'),
                'error'     => $e->getMessage(),
            ]);
            report($e);

            return null;
        }

        if (is_null($response)) {
            Log::warning('Inertia SSR returned empty response, falling back to CSR', [
                'url'       => Arr::get($page, 'url'),
                'component' => Arr::get($page, 'component'),
            ]);

            return null;
        }

        return new Response(
            implode("\n", $response['head']),
            $response['body']
        );
    }
}
