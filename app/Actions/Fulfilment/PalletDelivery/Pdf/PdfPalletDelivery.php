<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Jul 2024 14:54:12 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\PalletDelivery\Pdf;

use App\Actions\Traits\WithExportData;
use App\Models\Fulfilment\PalletDelivery;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as PDF;
use Symfony\Component\HttpFoundation\Response;

class PdfPalletDelivery
{
    use AsAction;
    use WithAttributes;
    use WithExportData;

    /**
     * @throws \Mpdf\MpdfException
     */
    public function handle(PalletDelivery $palletDelivery)
    {

        $config = [
            'title'                  => $palletDelivery->reference,
            'margin_left'            => 8,
            'margin_right'           => 8,
            'margin_top'             => 2,
            'margin_bottom'          => 2,
            'auto_page_break'        => true,
            'auto_page_break_margin' => 10
        ];

        return PDF::chunkLoadView('<html-separator/>', 'pickings.templates.pdf.delivery', [
            'filename' => $palletDelivery->reference,
            'delivery' => $palletDelivery,
            'customer' => $palletDelivery->fulfilmentCustomer->customer,
            'shop'     => $palletDelivery->fulfilment->shop
        ], [], $config);
    }

    /**
     * @throws \Mpdf\MpdfException
     */
    public function asController(PalletDelivery $palletDelivery, ActionRequest $request): Response
    {
        $filename = 'pallet-delivery-' . $palletDelivery->slug . '.pdf';

        $pdf = $this->handle($palletDelivery);

        return response($pdf->stream($filename . '.pdf'), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="' . $filename . '.pdf"');

    }
}
