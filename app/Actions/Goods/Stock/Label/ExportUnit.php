<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 13:31:13 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\Stock\Label;

use App\Actions\Traits\WithExportData;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as PDF;
use Symfony\Component\HttpFoundation\Response;

class ExportUnit
{
    use AsAction;
    use WithAttributes;
    use WithExportData;

    /**
     * @throws \Mpdf\MpdfException
     */
    public function handle(ActionRequest $request): Response
    {
        $width      = $request->get('width');
        $height     = $request->get('height');
        $customText = $request->get('custom_text');
        $withImage  = $request->get('with_image');

        $filename = 'unit-' . now()->format('Y-m-d');

        $config = [
            'title'                  => $filename,
            'format'                 => [$width, $height],
            'margin_left'            => 2,
            'margin_right'           => 2,
            'margin_top'             => 2,
            'margin_bottom'          => 2,
            'auto_page_break'        => true,
            'auto_page_break_margin' => 10
        ];

        $headerFontSize      = 2.5;
        $contentFontSize     = 2;
        $labelFontSize       = 1.5;
        $barcodeSize         = 1;
        $imageSize           = "120px";
        $signature_font_size = "10px";
        $font_size           = "16px";
        $text_margin         = "4px";

        if ($width == 63) {
            $config['default_font_size'] = '2';
            $headerFontSize              = 1.0;
            $contentFontSize             = 0.8;
            $labelFontSize               = 0.6;
            $barcodeSize                 = 0.3;
        }

        if ($width == 63.5) {
            $config['default_font_size'] = '2';
            $headerFontSize              = 1.0;
            $contentFontSize             = 0.8;
            $labelFontSize               = 0.6;
            $barcodeSize                 = 0.3;
        }

        if ($width == 70) {
            $config['default_font_size'] = '2';
            $headerFontSize              = 1.0;
            $contentFontSize             = 0.8;
            $labelFontSize               = 0.6;
            $barcodeSize                 = 0.3;
        }

        if ($width == 125) {
            $config['default_font_size'] = '3';
            $headerFontSize              = 1.5;
            $contentFontSize             = 1.0;
            $labelFontSize               = 1.0;
            $barcodeSize                 = 0.3;
        }

        if ($width == 130) {
            $config['default_font_size'] = '4';
            $headerFontSize              = 2.5;
            $contentFontSize             = 2.0;
            $labelFontSize               = 1.5;
            $barcodeSize                 = 1.0;
            $signature_font_size         = "12px";
        }

        if ($width == 140) {
            $config['default_font_size'] = '30';
            $headerFontSize              = 3.5;
            $contentFontSize             = 3.0;
            $labelFontSize               = 2.5;
            $barcodeSize                 = 2;
            $signature_font_size         = "18px";
            $font_size                   = "26px";
            $text_margin                 = "8px";
        }

        $material = 'Zea Mays (Corn Starch) , Polyvinyl Alcohol , Aqua , Sodium Dodecyl Sulphate ,
            Cocamide DEA , Propylene Glycol , Paraffinum Liquidum , Parfum ,
            Methylisothiazolinone , (+/- CI 16035 , CI 19140 , CI 42090 , CI 18050 , CI 16255 , CI
            45430 , CI 15985)';

        $pdf = PDF::loadView('labels.templates.pdf.unit', [
            'filename'            => $filename,
            'headerFontSize'      => $headerFontSize,
            'contentFontSize'     => $contentFontSize,
            'labelFontSize'       => $labelFontSize,
            'barcodeSize'         => $barcodeSize,
            'customText'          => $customText,
            'material'            => $material,
            'signature_font_size' => $signature_font_size,
            'font_size'           => $font_size,
            'text_margin'         => $text_margin,
            'withImage'           => $withImage == "true",
            'imageSize'           => $imageSize
        ], [], $config);

        return response($pdf->stream($filename . '.pdf'), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="' . $filename . '.pdf"');
    }

    /**
     * @throws \Mpdf\MpdfException
     */
    public function asController(ActionRequest $request): Response
    {
        return $this->handle($request);
    }
}
