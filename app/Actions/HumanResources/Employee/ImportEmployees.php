<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 21 Sep 2023 11:34:12 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\Employee;

use App\Actions\Helpers\Upload\ImportUpload;
use App\Actions\Helpers\Upload\StoreUpload;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesAuthorisation;
use App\Actions\Traits\WithImportModel;
use App\Imports\HumanResources\Employee\EmployeeImport;
use App\Models\Helpers\Upload;
use App\Models\HumanResources\Employee;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\ActionRequest;

class ImportEmployees extends OrgAction
{
    use WithImportModel;
    use WithHumanResourcesAuthorisation;


    public function handle(Organisation $parent, $file): Upload
    {
        $upload = StoreUpload::run($file, Employee::class);

        if ($this->isSync) {
            ImportUpload::run(
                $file,
                new EmployeeImport($parent, $upload)
            );
            $upload->refresh();
        } else {
            ImportUpload::dispatch(
                $this->tmpPath.$upload->filename,
                new EmployeeImport($parent, $upload)
            );
        }

        return $upload;
    }

    public string $commandSignature = 'employee:import {org} {--g|g_drive} {filename}';

    public function runImportForCommand($file, $command): Upload
    {
        $organisation = Organisation::where('slug', $command->argument('org'))->firstOrFail();

        return $this->handle($organisation, $file);
    }

    public function asController(Organisation $organisation, ActionRequest $request): Upload
    {
        $this->initialisation($organisation, $request);

        $file = $request->file('file');
        Storage::disk('local')->put($this->tmpPath, $file);

        return $this->handle($organisation, $file);
    }


}
