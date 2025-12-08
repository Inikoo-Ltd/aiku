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
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\Concerns\AsAction;

class CheckCatalogueFileExistsInR2
{
    use AsAction;

    public function handle(string $filePath): bool
    {

        $disk = Storage::disk('catalogue-iris-r2');

        try {
            $exists = $disk->exists($filePath);

            return $exists;
        } catch (Exception $e) {
            Log::error('R2 Check File Exists Error: '.$e->getMessage());

            return false;
        }
    }

    public function getCommandSignature(): string
    {
        return 'check:catalogue-file-exists-in-r2 {filePath}';
    }

    public function asCommand(Command $command): bool
    {
        return $this->handle($command->argument('filePath'));
    }
}
