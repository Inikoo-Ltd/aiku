<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 06-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Helpers\Isdoc;

use Lorisleiva\Actions\Concerns\AsAction;

class DeleteTempIsdoc
{
    use AsAction;

    public string $commandSignature =  'isdoc:delete_temp';
    public string $commandDescription = 'Delete isdoc temp';

    public function handle(): void
    {
        $location = storage_path('app/tmp/isdoc');

        if (is_dir($location)) {
            $files = glob($location . '/*');

            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }

    public function asCommand($command)
    {

        $this->handle();
    }


}
