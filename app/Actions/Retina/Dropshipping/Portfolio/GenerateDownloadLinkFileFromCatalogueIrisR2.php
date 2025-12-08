<?php

/*
 * Author: Eka Yudinata <ekayudinatha@gmail.com>
 * Created: Thu, 13 Nov 2025 17:06:15 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Eka Yudinata
 */

namespace App\Actions\Retina\Dropshipping\Portfolio;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class GenerateDownloadLinkFileFromCatalogueIrisR2
{
    use AsAction;

    public function handle(string $filePath): ?string
    {
        try {
            $fileExists = CheckCatalogueFileExistsInR2::run($filePath);

            if (! $fileExists) {
                Log::warning('File not found in R2: '.$filePath);

                return null;
            }

            // Build URL manually using your R2 public domain
            $publicDomain = config('filesystems.disks.catalogue-iris-r2.url');

            return rtrim($publicDomain, '/').'/'.ltrim($filePath, '/');

        } catch (Exception $e) {
            Log::error('R2 Generate Download Link Error: '.$e->getMessage());

            return null;
        }
    }

    public function getCommandSignature(): string
    {
        return 'generate:download-link-file-from-catalogue-iris-r2 {filePath}';
    }

    public function asCommand(Command $command): int
    {
        $filePath = $command->argument('filePath');
        $command->info("Generating download link for file: {$filePath}");

        $url = $this->handle($filePath);

        if ($url === null) {
            $command->error("❌ Failed to generate download link for file: {$filePath}");

            return 1; // Non-zero exit code for error
        }

        $command->info('✅ Successfully generated download link');
        $command->line('🔗 Download URL: '.$url);

        return 0; // Zero exit code for success
    }
}
