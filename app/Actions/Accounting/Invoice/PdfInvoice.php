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
use Illuminate\Support\Arr;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Symfony\Component\HttpFoundation\Response;
use App\Actions\Traits\Authorisations\WithInvoiceDownloadAuthorisation;

class PdfInvoice extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithExportData;
    use WithInvoicesExport;
    use WithInvoiceDownloadAuthorisation;


    public function handle(Invoice $invoice, array $options = []): Response
    {
        $locale        = Arr::pull($options, 'lang');
        $exportDetails = Arr::only($options, ['incoterms', 'net_weight']);
        $options       = Arr::except($options, ['incoterms', 'net_weight']);

        if ($options || $exportDetails) {
            $data = $invoice->data;
            if ($options) {
                $data['pdf_columns'] = $options + Arr::get($data, 'pdf_columns', []);
            }
            if ($exportDetails) {
                $data['export_details'] = array_filter(
                    $exportDetails + Arr::get($data, 'export_details', []),
                    fn ($value) => $value !== null && $value !== ''
                );
            }
            $invoice->updateQuietly(['data' => $data]);
        }

        return $this->processDataExportPdf($invoice, $locale);
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
            'export_by_tariff_code' => ['sometimes', 'boolean'],
            'show_dispatch_totals' => ['sometimes', 'boolean'],
            'show_batch_code'      => ['sometimes', 'boolean'],
            'separate_out_of_stock' => ['sometimes', 'boolean'],
            'show_discounts'        => ['sometimes', 'boolean'],
            'lang'                  => ['sometimes', 'string', 'in:en'],
            'incoterms'             => ['sometimes', 'nullable', 'string', 'max:100'],
            'net_weight'            => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:1000000'],
        ];
    }

    public function asController(Organisation $organisation, Invoice $invoice, ActionRequest $request): Response
    {
        $this->initialisationFromShop($invoice->shop, $request);

        return $this->handle($invoice, $this->validatedData);
    }
}
