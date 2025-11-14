<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 14 Nov 2025 17:06:15 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Portfolio;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\Concerns\AsAction;

class UploadFileToCatalogueIrisR2
{
    use AsAction;

    public function handle(string $sourcePath, string $destinationPath): bool
    {

        $disk = Storage::disk('catalogue-iris-r2');

        try {
            // get file contents from source path
            $fileContents = file_get_contents($sourcePath);

            return $disk->put(
                $destinationPath,
                $fileContents,
                'public'
            );
        } catch (Exception $e) {
            Log::error('R2 Upload Error: ' . $e->getMessage());
            return false;
        }
    }

    public function getCommandSignature(): string
    {
        return 'upload:file-to-catalogue-iris-r2 {sourcePath} {destinationPath}';
    }

    public function asCommand(Command $command): bool
    {
        return $this->handle($command->argument('sourcePath'), $command->argument('destinationPath'));
    }




}
