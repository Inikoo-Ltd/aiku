<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 18 Sep 2023 18:48:13 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer;

use App\Actions\Helpers\Upload\ImportUpload;
use App\Actions\Helpers\Upload\StoreUpload;
use App\Actions\Traits\WithImportModel;
use App\Imports\CRM\CustomerImport;
use App\Models\Helpers\Upload;
use App\Models\SysAdmin\Group;

class ImportCustomers
{
    use WithImportModel;

    public function handle(Group $group, $file): Upload
    {
        $upload = StoreUpload::make()->fromFile(
            $group,
            $file,
            [
                'model'       => 'Customer',
                'parent_type' => $group->getMorphClass(),
                'parent_id'   => $group->id,
            ]
        );

        if ($this->isSync) {
            ImportUpload::run(
                $file,
                new CustomerImport($upload)
            );
            $upload->refresh();
        } else {
            ImportUpload::dispatch(
                $this->tmpPath.$upload->filename,
                new CustomerImport($upload)
            );
        }

        return $upload;
    }

    public string $commandSignature = 'customer:import {group} {--g|g_drive} {filename}';

    public function runImportForCommand($file, $command): Upload
    {
        $group = Group::where('slug', $command->argument('group'))->firstOrFail();

        return $this->handle($group, $file);
    }

}
