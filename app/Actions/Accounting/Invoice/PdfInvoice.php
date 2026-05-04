<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 24 Apr 2023 20:23:18 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Invoice;

use App\Actions\OrgAction;
use App\Actions\Traits\WithExportData;
use App\Models\Accounting\Invoice;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Symfony\Component\HttpFoundation\Response;

class PdfInvoice extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithExportData;
    use WithInvoicesExport;


    public function handle(Invoice $invoice, array $options = []): Response
    {
        return $this->processDataExportPdf($invoice, $options);
    }

    public function rules(): array
    {
        return [
            'pro_mode'             => ['sometimes', 'boolean'],
            'country_of_origin'    => ['sometimes', 'boolean'],
            'rrp'                  => ['sometimes', 'boolean'],
            'parts'                => ['sometimes', 'boolean'],
            'commodity_codes'      => ['sometimes', 'boolean'],
            'weight'               => ['sometimes', 'boolean'],
            'barcode'              => ['sometimes', 'boolean'],
            'cpnp'                 => ['sometimes', 'boolean'],
            'hide_payment_status'  => ['sometimes', 'boolean'],
            'group_by_tariff_code' => ['sometimes', 'boolean'],
        ];
    }

    public function asController(Organisation $organisation, Invoice $invoice, ActionRequest $request): Response
    {
        $this->initialisationFromShop($invoice->shop, $request);

        return $this->handle($invoice, $this->validatedData);
    }
}
