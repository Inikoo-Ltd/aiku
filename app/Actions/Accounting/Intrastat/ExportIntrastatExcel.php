<?php

/*
 * Author: Koding Aiku
 * Created: Tue, 17 Mar 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Intrastat;

use App\Actions\OrgAction;
use App\Actions\Traits\WithExportData;
use App\Exports\Accounting\IntrastatExportExcel;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportIntrastatExcel extends OrgAction
{
    use WithExportData;

    public function authorize(ActionRequest $request): bool
    {
        return in_array(
            $this->organisation->id,
            $request->user()->authorisedOrganisations()->pluck('id')->toArray()
        );
    }

    public function handle(Organisation $organisation, array $filters): BinaryFileResponse
    {
        $export = new IntrastatExportExcel($organisation, $filters);

        return $this->export($export, 'intrastat-export', $filters['type'] ?? 'xlsx');
    }

    public function asController(Organisation $organisation, ActionRequest $request): BinaryFileResponse
    {
        $this->initialisation($organisation, $request);

        $this->setRawAttributes($request->all());
        $this->validateAttributes();

        $filters = [
            'type'     => $request->input('type', 'xlsx'),
            'between'  => $request->input('between', []),
            'elements' => $request->input('elements', []),
        ];

        return $this->handle($organisation, $filters);
    }
}
