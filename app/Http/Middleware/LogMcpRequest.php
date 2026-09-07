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

            $error = $this->responseError($response->getContent() ?: '');

            McpRequest::create([
                'group_id'    => $user->group_id,
                'user_id'     => $user->id,
                'tool'        => Arr::get($payload, 'params.name', 'unknown'),
                'arguments'   => Arr::get($payload, 'params.arguments', []),
                'is_error'    => $error !== null,
                'error'       => $error,
                'duration_ms' => (int) ((microtime(true) - $request->attributes->get('mcp_request_start', microtime(true))) * 1000),
            ]);
        } catch (Throwable $e) {
            Log::warning('Failed to log MCP request', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Tool-level failures set isError with the message in content[].text; protocol
     * failures such as calling a tool the user does not have come back as a top-level
     * JSON-RPC error object. Responses may be plain JSON or an SSE stream of data: lines.
     */
    protected function responseError(string $content): ?string
    {
        foreach (preg_split('/\R/', $content) as $line) {
            $line = preg_replace('/^data:\s*/', '', trim($line));
            $decoded = json_decode($line, true);
            if (!is_array($decoded)) {
                continue;
            }
            if (isset($decoded['error'])) {
                return mb_substr((string) Arr::get($decoded, 'error.message', json_encode($decoded['error'])), 0, 2000);
            }
            if (Arr::get($decoded, 'result.isError')) {
                return mb_substr((string) Arr::get($decoded, 'result.content.0.text', 'error'), 0, 2000);
            }
        }

        return null;
    }
}
