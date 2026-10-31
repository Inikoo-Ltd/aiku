<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 13 Jul 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Services;

use Exception;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\StrayRequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
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
    public const string FALLBACK_ATTRIBUTE = 'inertia_ssr_fallback';

    /**
     * Never posts to the Vite hot server (v3's /__inertia_ssr): our SSR is the
     * separate bun process at inertia.ssr.url, in dev as in production.
     */
    public function dispatch(array $page, ?Request $request = null): ?Response
    {
        if (!$this->ssrIsEnabled($request ?? request())) {
            return null;
        }

        if ($this->shouldEnsureBundleExists() && !$this->bundleExists()) {
            return null;
        }

        try {
            $response = $this->pendingRequest()
                ->connectTimeout(2)
                ->timeout(5)
                ->withOptions(['expect' => false])
                ->post($this->getProductionUrl('/render'), $page)
                ->throw()
                ->json();
        } catch (RequestException $e) {
            Log::warning('Inertia SSR request rejected, falling back to CSR', [
                'url'       => Arr::get($page, 'url'),
                'component' => Arr::get($page, 'component'),
                'status'    => $e->response->status(),
            ]);

            if ($e->response->serverError()) {
                report($e);
            }

            return $this->fallBackToClientRendering();
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

            return $this->fallBackToClientRendering();
        }

        if (is_null($response)) {
            Log::warning('Inertia SSR returned empty response, falling back to CSR', [
                'url'       => Arr::get($page, 'url'),
                'component' => Arr::get($page, 'component'),
            ]);

            return $this->fallBackToClientRendering();
        }

        return new Response(
            implode("\n", $response['head']),
            $response['body']
        );
    }

    private function fallBackToClientRendering(): null
    {
        request()->attributes->set(self::FALLBACK_ATTRIBUTE, true);

        return null;
    }
}
