<?php

/*
 * Author: Steven Wicca <stewicalf@gmail.com>
 * Created: Thu, 18 Dec 2025 13:00:00 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2025
 */

namespace App\Actions\Helpers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\Concerns\AsAction;

class HitIrisUrl
{
    use AsAction;

    public string $commandSignature = 'iris:hit-url {url} {--inertia=false : true|false} {--xmlhttp=false : true|false}';

    public function handle(string $url, bool $inertia = false, bool $xmlHttp = false): array
    {
        $headers = [];
        $startTime = microtime(true);

        if ($inertia) {
            $headers['X-Inertia'] = 'true';
        }

        if ($xmlHttp) {
            $headers['X-Requested-With'] = 'XMLHttpRequest';
        }

        $response = Http::withHeaders($headers)->withoutVerifying()->get($url);

        $contentType = $response->header('Content-Type') ?? '';
        $statusCode = $response->status();

        $isValid = true;

        if ($inertia) {
            $isValid = str_contains($contentType, 'application/json');
        } else {
            $isValid = str_contains($contentType, 'text/html');
        }

        $durationMs = round((microtime(true) - $startTime) * 1000);

        if (!$isValid) {
            try {
                DB::connection('aiku')->table('request_response_logs')->insert([
                    'url'           => $url,
                    'method'        => 'GET',
                    'x_inertia'     => $inertia ? 'true' : '',
                    'headers'       => json_encode($headers),
                    'request_body'  => null,
                    'response_body' => substr($response->body(), 0, 5000),
                    'status_code'   => $statusCode,
                    'content_type'  => $contentType,
                    'ip_address'    => null,
                    'duration_ms'   => $durationMs,
                    'created_at'    => now(),
                ]);
            } catch (\Exception $e) {
                //
            }
        }

        return [
            'url'              => $url,
            'x_inertia'        => $inertia,
            'x_requested_with' => $xmlHttp,
            'status'           => $statusCode,
            'content_type'     => $contentType,
            'is_valid'         => $isValid,
            'duration_ms'      => $durationMs,
            'body_preview'     => substr($response->body(), 0, 1000),
        ];
    }

    public function asCommand(\Illuminate\Console\Command $command): int
    {
        $result = $this->handle(
            $command->argument('url'),
            filter_var($command->option('inertia'), FILTER_VALIDATE_BOOLEAN),
            filter_var($command->option('xmlhttp'), FILTER_VALIDATE_BOOLEAN)
        );

        $command->info("URL              : {$result['url']}");
        $command->info("X-Inertia        : {$result['x_inertia']}");
        $command->info("X-Requested-With : " . ($result['x_requested_with'] ? 'XMLHttpRequest' : 'not set'));
        $command->info("Status           : {$result['status']}");
        $command->info("Content-Type     : {$result['content_type']}");
        $command->info("Is Valid         : " . ($result['is_valid'] ? 'Yes' : 'No'));
        $command->info("Duration         : {$result['duration_ms']} ms");

        if (!$result['is_valid']) {
            $command->warn('Response validation failed - logged to database');
        }

        $command->line("Body Preview:");
        $command->line($result['body_preview']);

        return 0;
    }
}
