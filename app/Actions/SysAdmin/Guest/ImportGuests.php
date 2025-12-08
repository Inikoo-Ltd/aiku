<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 16 Aug 2023 08:09:28 Malaysia Time, Pantai Lembeng, Bali
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Guest;

use App\Actions\Helpers\Upload\ImportUpload;
use App\Actions\Helpers\Upload\StoreUpload;
use App\Actions\Traits\WithImportModel;
use App\Imports\Auth\GuestImport;
use App\Models\Helpers\Upload;
use App\Models\SysAdmin\Guest;

class ImportGuests
{
    use WithImportModel;

    public function handle($file): Upload
    {
        $upload = StoreUpload::run($file, Guest::class);

        if ($this->isSync) {
            ImportUpload::run(
                $file,
                new GuestImport($upload)
            );
            $upload->refresh();
        } else {
            ImportUpload::dispatch(
                $this->tmpPath.$upload->filename,
                new GuestImport($upload)
            );
        }

        return $upload;

    }

    public string $commandSignature = 'guest:import {--g|g_drive} {filename}';
}
