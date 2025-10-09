<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Notifications\SlackTestNotification;
use Illuminate\Notifications\AnonymousNotifiable;

class TestSlackNotification extends Command
{
    protected $signature = 'slack:test {message?}';
    protected $description = 'Send a test notification to Slack';

    public function handle()
    {
        $message = $this->argument('message') ?? 'Hello from Laravel! 🚀';

        try {
            $notifiable = (new AnonymousNotifiable())
                ->route('slack', []);

            $notifiable->notify(new SlackTestNotification($message));

            $this->info('✅ Slack notification sent successfully!');
        } catch (\Exception $e) {
            $this->error('❌ Failed to send notification: ' . $e->getMessage());
        }
    }
}
