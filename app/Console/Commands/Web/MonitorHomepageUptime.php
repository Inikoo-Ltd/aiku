<?php

namespace App\Console\Commands\Web;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\Web\Website;
use App\Models\Web\WebsiteHealthLog;

class MonitorHomepageUptime extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitor:homepage-uptime {--url= : Specific URL to check}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor homepage uptime and notify Discord on failure';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('url')) {
            $urls = [$this->option('url')];
        } else {
            $urls = Website::where('migrated', true)
                ->where('status', true)
                ->get()
                ->map(fn($website) => 'https://' . $website->domain)
                ->filter()
                ->toArray();
        }

        $failedChecks = [];

        foreach ($urls as $url) {
            $result = $this->checkWebsite($url);
            if (!$result['is_up']) {
                $failedChecks[] = $result;
            }
        }

        $this->notifyDiscord($failedChecks);

        // Clear website health logs older than 7 days
        $deleted = WebsiteHealthLog::where('created_at', '<', now()->subDays(7))->delete();
        if ($deleted > 0) {
            $this->info("Pruned {$deleted} old website health logs.");
        }

        return 0;
    }

    protected function checkWebsite(string $url): array
    {
        try {
            $response = Http::timeout(10)->get($url);
            $isUp = $response->successful();
            $statusCode = $response->status();
            $errorMessage = $isUp ? null : 'Received status code: ' . $statusCode;
        } catch (\Exception $e) {
            $isUp = false;
            $statusCode = null;
            $errorMessage = $e->getMessage();
        }

        if (!$isUp) {
            $latestDeployment = \App\Models\DevOps\AppDeployment::latest()->first();

            WebsiteHealthLog::create([
                'url' => $url,
                'is_up' => $isUp,
                'status_code' => $statusCode,
                'error_message' => $errorMessage,
                'last_deployment_date' => $latestDeployment?->created_at,
            ]);
        }

        return [
            'url' => $url,
            'is_up' => $isUp,
            'status_code' => $statusCode,
            'error_message' => $errorMessage,
        ];
    }

    protected function notifyDiscord(array $failedChecks): void
    {
        if (empty($failedChecks)) {
            return; // Do not send message if all is ok
        }

        $webhookUrl = config('services.discord.webhook_url');

        if (!$webhookUrl) {
            $this->error('Discord webhook URL is not configured. Please set it in config/services.php or .env');
            return;
        }

        $message = "🚨 **Website Down Alert** 🚨 <@&1164019425154969600>\n\n";
        foreach ($failedChecks as $fail) {
            $status = $fail['status_code'] ?? 'N/A';
            $error = $fail['error_message'] ?? 'Unknown error';
            $message .= "**URL:** {$fail['url']}\n**Status Code:** {$status}\n**Error:** {$error}\n\n";
        }

        try {
            Http::post($webhookUrl, [
                'content' => $message,
            ]);
        } catch (\Exception $e) {
            $this->error('Failed to send Discord notification: ' . $e->getMessage());
        }
    }
}
