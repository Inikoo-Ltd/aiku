<?php

namespace App\Actions\Accounting\SageInvoices;

use App\Actions\OrgAction;
use App\Actions\Traits\WithExportData;
use App\Exports\Accounting\SageInvoicesExport;
use App\Models\SysAdmin\Organisation;
use Carbon\Carbon;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportSageInvoices extends OrgAction
{
    use WithExportData;

    public function handle(Organisation $organisation, array $filters): BinaryFileResponse
    {
        $type = $filters['type'] ?? 'xlsx';

        $startDate = null;
        $endDate = null;

        if (!empty($filters['between']['date'])) {
            $raw = $filters['between']['date'];
            [$start, $end] = explode('-', $raw);

            $startDate = Carbon::createFromFormat('Ymd', $start)->format('Y-m-d');
            $endDate = Carbon::createFromFormat('Ymd', $end)->format('Y-m-d');
        }

        if (!$startDate) {
            $startDate = Carbon::now()->startOfMonth()->format('Y-m-d');
        }

        if (!$endDate) {
            $endDate = Carbon::now()->endOfMonth()->format('Y-m-d');
        }

        $export = new SageInvoicesExport($organisation, $startDate, $endDate);

        $filename = sprintf(
            'sage_invoices_%s_%s_to_%s',
            $organisation->slug,
            Carbon::parse($startDate)->format('Y-m-d'),
            Carbon::parse($endDate)->format('Y-m-d')
        );

        return $this->export($export, $filename, $type);
    }

    public function asController(Organisation $organisation, ActionRequest $request): BinaryFileResponse
    {
        $this->initialisation($organisation, $request);

        $this->setRawAttributes($request->all());
        $this->validateAttributes();

        $filters = [
            'type' => $request->input('type', 'xlsx'),
            'between' => $request->input('between', [])
        ];

        return $this->handle($organisation, $filters);
    }
}
