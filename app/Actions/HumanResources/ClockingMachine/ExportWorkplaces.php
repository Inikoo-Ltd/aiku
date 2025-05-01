<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 24 Apr 2023 20:23:18 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\ClockingMachine;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesAuthorisation;
use App\Actions\Traits\WithExportData;
use App\Exports\HumanResources\WorkplacesExport;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportWorkplaces extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithExportData;
    use WithHumanResourcesAuthorisation;

    /**
     * @throws \Throwable
     */
    public function handle(array $modelData): BinaryFileResponse
    {
        $type = $modelData['type'];

        return $this->export(new WorkplacesExport(), 'workplaces', $type);
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:csv,xlsx'],
        ];
    }


    /**
     * @throws \Throwable
     */
    public function asController(Organisation $organisation, ActionRequest $request): BinaryFileResponse
    {
        $this->initialisation($organisation, $request);

        return $this->handle($this->validatedData);
    }
}
