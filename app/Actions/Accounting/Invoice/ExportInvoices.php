<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 24 Apr 2023 20:23:18 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Invoice;

use App\Actions\OrgAction;
use App\Actions\Traits\WithExportData;
use App\Exports\Accounting\InvoicesExport;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportInvoices extends OrgAction
{
    use WithExportData;

    /**
     * @throws \Throwable
     */
    public function handle(array $modelData): BinaryFileResponse
    {
        $type = $modelData['type'];

        return $this->export(new InvoicesExport(), 'invoices', $type);
    }


    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:pdf,xlsx,csv'],

        ];
    }

    /**
     * @throws \Throwable
     */
    public function asController(ActionRequest $request): BinaryFileResponse
    {
        $this->initialisationFromGroup(group(), $request);
        return $this->handle($this->validatedData);
    }
}
