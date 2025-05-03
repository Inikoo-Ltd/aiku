<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 21 Sep 2023 08:23:57 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\Employee;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesAuthorisation;
use App\Exports\HumanResources\EmployeesTemplateExport;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DownloadEmployeesTemplate extends OrgAction
{
    use WithHumanResourcesAuthorisation;

    /**
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function handle(): BinaryFileResponse
    {
        return Excel::download(new EmployeesTemplateExport(), 'employees_template.xlsx');
    }

    /**
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function asController(Organisation $organisation, ActionRequest $request): BinaryFileResponse
    {
        $this->initialisation($organisation, $request);

        return $this->handle();
    }

}
