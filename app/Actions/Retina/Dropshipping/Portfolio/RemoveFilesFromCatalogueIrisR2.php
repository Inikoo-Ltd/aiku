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

class RemoveFilesFromCatalogueIrisR2
{
    use AsAction;

    // @param array $destinationPaths
    public function handle(array $destinationPaths): bool
    {

        $disk = Storage::disk('catalogue-iris-r2');

        try {
            return $disk->delete($destinationPaths);
        } catch (Exception $e) {
            Log::error('R2 Remove Error: ' . $e->getMessage());
            return false;
        }
    }

    public function getCommandSignature(): string
    {
        return 'remove:file-from-catalogue-iris-r2 {destinationPath*}';
    }

    public function asCommand(Command $command): bool
    {
        return $this->handle($command->argument('destinationPath'));
    }
}
