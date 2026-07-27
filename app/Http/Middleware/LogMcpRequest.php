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
                'is_error'    => str_contains($response->getContent() ?: '', '"isError":true'),
                'duration_ms' => (int) ((microtime(true) - $request->attributes->get('mcp_request_start', microtime(true))) * 1000),
            ]);
        } catch (Throwable $e) {
            Log::warning('Failed to log MCP request', ['error' => $e->getMessage()]);
        }
    }
}
