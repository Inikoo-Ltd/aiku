<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 09 Jul 2026 22:43:48 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Reports\SageInvoices;

use App\Actions\OrgAction;
use App\Actions\Traits\WithExportData;
use App\Exports\Accounting\SageInvoicesExport;
use App\Models\SysAdmin\Organisation;
use Carbon\Carbon;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportSageInvoices extends OrgAction
{
    use WithExportData;

    private const STREAM_THRESHOLD = 20000;

    public function handle(Organisation $organisation, array $filters): BinaryFileResponse|StreamedResponse
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

        $export = new SageInvoicesExport($organisation, $startDate, $endDate);

        $range = $startDate && $endDate
            ? Carbon::parse($startDate)->format('Y-m-d') . '_to_' . Carbon::parse($endDate)->format('Y-m-d')
            : 'all';

        $filename = sprintf('sage_invoices_%s_%s', $organisation->slug, $range);

        if ($type === 'xlsx' && $export->query()->toBase()->count() < self::STREAM_THRESHOLD) {
            return $this->export($export, $filename, $type);
        }

        return $this->streamMappedCsv($export, $filename);
    }

    public function asController(Organisation $organisation, ActionRequest $request): BinaryFileResponse|StreamedResponse
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
