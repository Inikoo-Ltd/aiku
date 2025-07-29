<?php

namespace App\Console\Commands;

use App\Events\AppVersionWebsocketEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateAppVersion extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-app-version';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate verdonk.json with current timestamp version';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        AppVersionWebsocketEvent::dispatch();

        // $version = now()->toISOString();

        // $content = json_encode([
        //     'version' => $version,
        // ], JSON_PRETTY_PRINT);

        // File::put(public_path('verdonk.json'), $content);

        // $this->info("verdonk.json updated with version: {$version}");
    }
}
