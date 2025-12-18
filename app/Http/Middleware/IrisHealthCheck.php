<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IrisHealthCheck
{
    public function handle(Request $request, Closure $next)
    {
        $startTime = microtime(true);

        $xInertia = $request->header('X-Inertia');

        \Log::info('IrisHealthCheck middleware triggered', [
            'url' => $request->fullUrl(),
            'x_inertia' => $xInertia,
        ]);

        $response = $next($request);

        $contentType = $response->headers->get('Content-Type') ?? '';

        $isValid = true;

        if ($xInertia === 'true') {
            $isValid = str_contains($contentType, 'application/json');
        }

        if (!$xInertia || $xInertia === 'false') {
            $isValid = str_contains($contentType, 'text/html');
        }

        \Log::info('IrisHealthCheck validation result', [
            'url' => $request->fullUrl(),
            'is_valid' => $isValid,
            'content_type' => $contentType,
        ]);

        if (!$isValid) {
            try {
                DB::connection('aiku')->table('request_response_logs')->insert([
                    'url'           => $request->fullUrl(),
                    'method'        => $request->method(),
                    'x_inertia'     => $xInertia,
                    'headers'       => json_encode($request->headers->all()),
                    'request_body'  => json_encode($request->all()),
                    'response_body' => substr($response->getContent(), 0, 5000),
                    'status_code'   => $response->getStatusCode(),
                    'content_type'  => $contentType,
                    'ip_address'    => $request->ip(),
                    'duration_ms'   => round((microtime(true) - $startTime) * 1000),
                    'created_at'    => now(),
                ]);

                \Log::info('IrisHealthCheck log inserted to database', [
                    'url' => $request->fullUrl(),
                ]);
            } catch (\Exception $e) {
                \Log::error('IrisHealthCheck middleware failed to log request', [
                    'error' => $e->getMessage(),
                    'url' => $request->fullUrl(),
                ]);
            }
        }

        return $response;
    }
}
