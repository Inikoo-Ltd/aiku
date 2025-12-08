<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 11 Jul 2023 12:40:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Exports\HumanResources;

use Maatwebsite\Excel\Concerns\FromArray;

class EmployeesTemplateExport implements FromArray
{
    public function array(): array
    {
        return [
            ['worker_number', 'name', 'alias', 'job_title', 'positions', 'starting_date', 'workplace', 'username', 'password', 'reset_password'],
        ];
    }
}
