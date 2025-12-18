<?php

/*
 * Author: Steven Wicca <stewicalf@gmail.com>
 * Created: Thu, 18 Dec 2025 13:00:00 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2025
 */

namespace App\Actions\Helpers;

use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\Concerns\AsAction;

class HitIrisUrl
{
    use AsAction;

    /**
     * Command:
     * php artisan iris:hit-url {url} --inertia=true|false|none
     */
    public string $commandSignature = 'iris:hit-url {url} {--inertia=none : true|false|none}';

    public function handle(string $url, string $inertia = 'none'): array
    {
        $headers = match ($inertia) {
            'true'  => ['X-Inertia' => 'true'],
            'false' => ['X-Inertia' => 'false'],
            default => [],
        };

        $response = Http::withHeaders($headers)->withoutVerifying()->get($url);

        return [
            'url'          => $url,
            'x_inertia'    => $inertia,
            'status'       => $response->status(),
            'content_type' => $response->header('Content-Type'),
            'body_preview' => substr($response->body(), 0, 1000),
        ];
    }

    public function asCommand(\Illuminate\Console\Command $command): int
    {
        $result = $this->handle(
            $command->argument('url'),
            $command->option('inertia')
        );

        $command->info("URL         : {$result['url']}");
        $command->info("X-Inertia   : {$result['x_inertia']}");
        $command->info("Status      : {$result['status']}");
        $command->info("Content-Type: {$result['content_type']}");
        $command->line("Body Preview:");
        $command->line($result['body_preview']);

        return 0;
    }
}
