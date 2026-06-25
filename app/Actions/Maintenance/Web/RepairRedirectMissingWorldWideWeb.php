<?php

namespace App\Actions\Maintenance\Web;

use Lorisleiva\Actions\Concerns\AsAction;
use Illuminate\Console\Command;
use Exception;
use Illuminate\Support\Facades\DB;
use App\Models\Web\Redirect;
use Illuminate\Support\Str;

class RepairRedirectMissingWorldWideWeb
{
    use AsAction;

    public function handle(?Command $command = null, ?bool $isDryRun = false): void
    {
        // Filtering shops that use www. subdomain
        $query = Redirect::where('from_url', 'NOT LIKE', 'https://www.%')->whereHas('shop', function ($query) {
            $query->where('is_aiku', true);
        });

        $totalRecords = $query->count();
        if ($totalRecords === 0) {
            $command?->info('All redirect URLs are already standardized with www.');
            return;
        }

        if ($isDryRun) {
            $command?->info("=== DRY RUN / PREVIEW MODE ===");
            $command?->info("Found {$totalRecords} records that need to be standardized. Showing first 20 examples:\n");
        } else {
            $command?->info("=== LIVE EXECUTION MODE ===");
            $command?->info("Processing {$totalRecords} records...");
            DB::beginTransaction();
        }

        $updatedCount = 0;

        try {
            $records = $isDryRun ? $query->limit(20)->get() : $query->get();

            foreach ($records as $redirect) {
                $originalUrl = $redirect->from_url;
                $newUrl = $originalUrl;

                if (Str::startsWith($originalUrl, 'https://v2.')) {
                    $newUrl = Str::replaceFirst('https://v2.', 'https://www.', $originalUrl);
                } elseif (Str::startsWith($originalUrl, 'https://') && !Str::startsWith($originalUrl, 'https://www.')) {
                    $newUrl = Str::replaceFirst('https://', 'https://www.', $originalUrl);
                }

                if ($newUrl !== $originalUrl) {
                    if ($isDryRun) {
                        $command?->line("<comment>[ID: {$redirect->id}]</comment> <fg=red>{$originalUrl}</> <fg=gray>---></> <fg=green>{$newUrl}</>");
                    } else {
                        $redirect->update(['from_url' => $newUrl]);
                    }
                    $updatedCount++;
                }
            }

            if ($isDryRun) {
                $command?->line("\n<info>Preview completed.</info> To apply these changes to the database, run:");
                $command?->line("<comment>php artisan repair:redirect-www --force</comment>");
            } else {
                DB::commit();
                $command?->info("Success! Updated {$updatedCount} redirect URLs to use 'https://www.'.");
            }
        } catch (Exception $e) {
            if (!$isDryRun) {
                DB::rollBack();
            }
            $command?->error("Repair failed: " . $e->getMessage());
            throw $e;
        }
    }

    public string $commandSignature = 'repair:redirect-www {--apply : Execute the structural updates instead of just previewing}';

    public function asCommand(Command $command): void
    {
        $isDryRun = !$command->option('apply');
        $this->handle($command, $isDryRun);
    }
}
