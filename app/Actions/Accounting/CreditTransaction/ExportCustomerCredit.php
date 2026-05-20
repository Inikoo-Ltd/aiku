<?php

namespace App\Actions\Accounting\CreditTransaction;

use App\Actions\OrgAction;
use App\Actions\Traits\WithExportData;
use App\Exports\Accounting\CustomerCreditExport;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportCustomerCredit extends OrgAction
{
    use AsAction;
    use WithExportData;

    /**
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function handle(Organisation $organisation, string $beforeDate, string $type = 'xlsx'): BinaryFileResponse
    {
        $export = new CustomerCreditExport($organisation, $beforeDate);

        $filename = sprintf('customer_credit_%s_before_%s', $organisation->slug, $beforeDate);

        return $this->export($export, $filename, $type);
    }

    /**
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function asController(Organisation $organisation, ActionRequest $request): BinaryFileResponse
    {
        $this->initialisation($organisation, $request);

        return $this->handle(
            $organisation,
            $request->input('before_date'),
            $request->input('type', 'xlsx')
        );
    }
}
