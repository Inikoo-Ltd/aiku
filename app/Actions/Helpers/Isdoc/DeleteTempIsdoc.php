<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 06-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Helpers\Isdoc;

use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteTempIsdoc
{
    use AsAction;

    public string $commandSignature =  'isdoc:delete_temp';
    public string $commandDescription = 'Delete isdoc temp';

    public function handle(): void
    {
        $disk = Storage::disk('local');
        $directory = 'tmp/isdoc';

        if ($disk->exists($directory)) {
            $files = $disk->files($directory);

            foreach ($files as $file) {
                $disk->delete($file);
            }
        }
    }

    public function asCommand($command): void
    {
        $this->handle();
        $command->line('Temporary ISDOC files deleted successfully.');
    }


}
