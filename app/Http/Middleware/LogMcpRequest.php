<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Jul 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use App\Models\SysAdmin\McpRequest;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class LogMcpRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('mcp_request_start', microtime(true));

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            $payload = json_decode($request->getContent(), true);

            if (Arr::get($payload, 'method') !== 'tools/call') {
                return;
            }

            $user = $request->user();
            if (!$user) {
                return;
            }

            McpRequest::create([
                'group_id'    => $user->group_id,
                'user_id'     => $user->id,
                'tool'        => Arr::get($payload, 'params.name', 'unknown'),
                'arguments'   => Arr::get($payload, 'params.arguments', []),
                'is_error'    => $this->responseHasError($response->getContent() ?: ''),
                'duration_ms' => (int) ((microtime(true) - $request->attributes->get('mcp_request_start', microtime(true))) * 1000),
            ]);
        } catch (Throwable $e) {
            Log::warning('Failed to log MCP request', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Tool-level failures set isError; protocol failures such as calling a tool the
     * user does not have come back as a top-level JSON-RPC error object. Tool payloads
     * are JSON-encoded strings with escaped quotes, so a bare "error":{ is protocol-level.
     */
    protected function responseHasError(string $content): bool
    {
        return str_contains($content, '"isError":true')
            || (bool) preg_match('/"error"\s*:\s*\{/', $content);
    }
}
