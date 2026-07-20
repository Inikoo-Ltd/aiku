<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 16 Jul 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Inventory\OrgStock\UI;

use App\Models\Inventory\OrgStock;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as PDF;
use Symfony\Component\HttpFoundation\Response;

class PdfOrgStockLabel
{
    use AsAction;

    /**
     * @throws \Mpdf\MpdfException
     */
    public function handle(OrgStock $orgStock, string $level, array $options): Response
    {
        $barcode = collect(GetOrgStockBarcodes::run($orgStock))->firstWhere('level', $level);

        if (! $barcode) {
            abort(404, __('Barcode not found for this level'));
        }

        $tradeUnit = $orgStock->tradeUnits->first();

        $width  = (float) ($options['width'] ?? 55);
        $height = (float) ($options['height'] ?? 45);

        $config = [
            'title'         => 'label-'.$orgStock->code,
            'format'        => [$width, $height],
            'margin_left'   => 2,
            'margin_right'  => 2,
            'margin_top'    => 2,
            'margin_bottom' => 2,
        ];

        $pdf = PDF::loadView('labels.templates.pdf.org_stock.label', [
            'code'          => $orgStock->code,
            'name'          => $tradeUnit?->name,
            'barcodeNumber' => $barcode['number'],
            'barcodeType'   => $barcode['level'] === 'unit' ? 'EAN13' : 'C128B',
        ], [], $config);

        $filename = 'label-'.$orgStock->code.'-'.$level.'.pdf';

        return response($pdf->stream($filename), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="'.$filename.'"');
    }

    /**
     * @throws \Mpdf\MpdfException
     */
    public function asController(Organisation $organisation, Warehouse $warehouse, OrgStock $orgStock, ActionRequest $request): Response
    {
        return $this->handle($orgStock, $request->input('level', 'unit'), $request->all());
    }
}
