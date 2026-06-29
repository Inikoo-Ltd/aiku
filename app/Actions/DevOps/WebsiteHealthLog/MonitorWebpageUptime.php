<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 29 Jun 2026 23:12:33 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\DevOps\WebsiteHealthLog;

use App\Models\DevOps\AppDeployment;
use App\Models\DevOps\WebsiteHealthLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\Concerns\AsAction;

class MonitorWebpageUptime
{
    use AsAction;

    protected function handle(string $url): array
    {
        try {
            $response     = Http::timeout(5)->get($url);
            $isUp         = $response->successful();
            $statusCode   = $response->status();
            $errorMessage = $isUp ? null : 'Received status code: '.$statusCode;
        } catch (\Exception $e) {
            $isUp         = false;
            $statusCode   = null;
            $errorMessage = $e->getMessage();
        }

        if (!$isUp) {
            $latestDeployment = AppDeployment::latest()->first();

            WebsiteHealthLog::create([
                'url'                  => $url,
                'is_up'                => $isUp,
                'status_code'          => $statusCode,
                'error_message'        => $errorMessage,
                'last_deployment_date' => $latestDeployment?->created_at,
            ]);

            $this->notifyDiscord($url, $statusCode, $errorMessage);
        }

        return [
            'url'           => $url,
            'is_up'         => $isUp,
            'status_code'   => $statusCode,
            'error_message' => $errorMessage,
        ];
    }

    public function getCommandSignature(): string
    {
        return 'monitor:webpage-uptime {url : Specific URL to check}';
    }

    public function getCommandDescription(): string
    {
        return 'Monitor webpage uptime and notify Discord on failure';
    }


    public function asCommand(Command $command): int
    {
        $this->handle($command->argument('url'));

        return 0;
    }


    protected function notifyDiscord(string $url, ?int $statusCode = null, ?string $errorMessage = null, ?Command $command = null): void
    {
        $webhookUrl = config('services.discord.webhook_url');

        if (!$webhookUrl) {
            $command?->error('Discord webhook URL is not configured. Please set it in config/services.php or .env');

            return;
        }

        $message = "🚨 **Website Down Alert** 🚨   <@&1164019425154969600>\n\n";
        $status  = $statusCode ?? 'N/A';
        $error   = $errorMessage ?? 'Unknown error';
        $message .= "**URL:** $url\n**Status Code:** $status\n**Error:** $error\n\n";


        try {
            Http::post($webhookUrl, [
                'content' => $message,
            ]);
        } catch (\Exception $e) {
            $command?->error('Failed to send Discord notification: '.$e->getMessage());
        }
    }
}
