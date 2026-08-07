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
use App\Models\SysAdmin\Group;

class ImportGuests
{
    use WithImportModel;

    public function handle(Group $group, $file): Upload
    {
        $upload = StoreUpload::make()->fromFile(
            $group,
            $file,
            [
                'model'       => 'Guest',
                'parent_type' => $group->getMorphClass(),
                'parent_id'   => $group->id,
            ]
        );

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

    public string $commandSignature = 'guest:import {group} {--g|g_drive} {filename}';

    public function runImportForCommand($file, $command): Upload
    {
        $group = Group::where('slug', $command->argument('group'))->firstOrFail();

        return $this->handle($group, $file);
    }
}
