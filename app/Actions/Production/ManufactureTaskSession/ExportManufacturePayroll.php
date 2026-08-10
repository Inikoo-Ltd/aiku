<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Aug 2026 12:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ManufactureTaskSession;

use App\Actions\OrgAction;
use App\Exports\Production\ManufacturePayrollExport;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\ActionRequest;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportManufacturePayroll extends OrgAction
{
    public function handle(Production $production, array $modelData): BinaryFileResponse
    {
        $from = Carbon::parse(Arr::get($modelData, 'from', now()->subWeek()->startOfWeek()->toDateString()));
        $to   = Carbon::parse(Arr::get($modelData, 'to', now()->subWeek()->endOfWeek()->toDateString()));

        $filename = sprintf('payroll-%s-%s-%s.csv', $production->slug, $from->toDateString(), $to->toDateString());

        return Excel::download(new ManufacturePayrollExport($production, $from, $to), $filename, ExcelWriter::CSV);
    }

    public function rules(): array
    {
        return [
            'from' => ['sometimes', 'date'],
            'to'   => ['sometimes', 'date', 'after_or_equal:from'],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo([
            'org-supervisor.'.$this->organisation->id,
            'productions-view.'.$this->organisation->id,
            "productions_operations.{$this->production->id}.orchestrate",
        ]);
    }

    public function asController(Organisation $organisation, Production $production, ActionRequest $request): BinaryFileResponse
    {
        $this->initialisationFromProduction($production, $request);

        return $this->handle($production, $this->validatedData);
    }
}
