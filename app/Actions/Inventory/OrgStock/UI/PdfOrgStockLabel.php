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
use Picqer\Barcode\BarcodeGenerator;
use Picqer\Barcode\BarcodeGeneratorPNG;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class PdfOrgStockLabel
{
    use AsAction;

    /**
     * The label stocks Aurora offered, kept to the millimetre so a roll bought for the old system
     * still prints straight out of this one.
     */
    public const SIZES = [
        '63x29.6'   => ['width' => 63.0, 'height' => 29.6],
        '63.5x29.6' => ['width' => 63.5, 'height' => 29.6],
        '70x29.7'   => ['width' => 70.0, 'height' => 29.7],
        '70x30'     => ['width' => 70.0, 'height' => 30.0],
        '125x37'    => ['width' => 125.0, 'height' => 37.0],
        '130x60'    => ['width' => 130.0, 'height' => 60.0],
        '140x90'    => ['width' => 140.0, 'height' => 90.0],
    ];

    /**
     * The outer packing label carries far less than the unit one, so Aurora only ever offered it on
     * the four stocks that suit a box, and those are the only ones offered here.
     */
    public const LEVEL_SIZES = [
        'unit' => ['63x29.6', '63.5x29.6', '70x29.7', '70x30', '125x37', '130x60', '140x90'],
        'sko'  => ['63x29.6', '63.5x29.6', '70x29.7', '130x60'],
    ];

    public const DEFAULT_SIZE = '63x29.6';

    /**
     * Only the unit label names the goods to whoever ends up holding them, so only it carries the
     * origin, the manufacturer, the weight and the account signature. The SKO label is a box label.
     */
    public const LEVEL_FIELDS = [
        'unit' => ['with_image', 'with_made_in', 'with_manufactured_by', 'with_weight', 'with_custom_text', 'with_account_signature'],
        'sko'  => ['with_image', 'with_custom_text'],
    ];

    /**
     * EU30161: 27 labels of 63.5 x 29.6mm on A4, three across and nine down. The margins are what
     * is left of the page once the die cuts are placed, split evenly between the two edges.
     */
    private const SHEET = [
        'columns'      => 3,
        'rows'         => 9,
        'cell_width'   => 63.5,
        'cell_height'  => 29.6,
        'column_gap'   => 2.5,
        'row_gap'      => 0.0,
        'page_width'   => 210.0,
        'page_height'  => 297.0,
    ];

    private const CELL_PADDING = 1.5;

    /**
     * The top is trimmed closer than the other three sides. A label is read from its code down, so
     * the code wants to sit against the top edge, and the glyph's own ascent already contributes
     * about a millimetre of apparent space above it before any margin is added.
     */
    private const TOP_PADDING = 0.8;

    private const CODE128_MODULE_MM = 0.3804;

    private const CODE128_HEIGHT_MM = 10.05;

    private const EAN13_WIDTH_MM = 36.94;

    private const EAN13_HEIGHT_MM = 25.48;

    /**
     * @throws \Mpdf\MpdfException
     */
    public function handle(OrgStock $orgStock, string $level, array $options): Response
    {
        $level = isset(self::LEVEL_FIELDS[$level]) ? $level : 'unit';
        $label = GetOrgStockLabelData::run($orgStock, $level);

        /* The unit label is built around its barcode, so without one there is nothing to print. The
           SKO label never carries one, and prints for a box that has not been given a number yet. */
        if ($level === 'unit' && blank($label['barcode']['number'])) {
            abort(404, __('This org stock has no barcode yet'));
        }

        $show     = $this->getVisibleFields($options, $label, $level);
        $isSheet  = ($options['layout'] ?? 'single') === 'sheet';
        $size     = $this->getSize($options, $level);
        $filename = 'label-'.$orgStock->code.'-'.$level.($isSheet ? '-a4' : '').'.pdf';

        $withBarcode = filled($label['barcode']['number']);

        $pdf = $isSheet
            ? $this->getSheetPdf($label, $show, $options, $level, $withBarcode, $filename)
            : $this->getSingleLabelPdf($label, $show, $options, $size, $level, $withBarcode, $filename);

        return response($pdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="'.$filename.'"');
    }

    /**
     * @throws \Mpdf\MpdfException
     */
    private function getSingleLabelPdf(array $label, array $show, array $options, array $size, string $level, bool $withBarcode, string $filename)
    {
        return PDF::loadView('labels.templates.pdf.org_stock.label', [
            'label'      => $label,
            'show'       => $show,
            'level'      => $level,
            'skoBarcode' => $this->getSkoBarcode($label, $level, $size['width'], $size['height']),
            'customText' => $this->getCustomText($options),
            'scale'      => $this->getScale($size['width'], $size['height'], $level, $show['image'], $withBarcode),
        ], [], [
            'title'         => $filename,
            'format'        => [$size['width'], $size['height']],
            'margin_left'   => self::CELL_PADDING,
            'margin_right'  => self::CELL_PADDING,
            'margin_top'    => self::TOP_PADDING,
            'margin_bottom' => self::CELL_PADDING,
            'margin_header' => 0,
            'margin_footer' => 0,
        ]);
    }

    /**
     * @throws \Mpdf\MpdfException
     */
    private function getSheetPdf(array $label, array $show, array $options, string $level, bool $withBarcode, string $filename)
    {
        $sheet = self::SHEET;

        return PDF::loadView('labels.templates.pdf.org_stock.label_sheet', [
            'label'       => $label,
            'show'        => $show,
            'level'       => $level,
            'skoBarcode'  => $this->getSkoBarcode($label, $level, $sheet['cell_width'], $sheet['cell_height']),
            'customText'  => $this->getCustomText($options),
            'scale'       => $this->getScale($sheet['cell_width'], $sheet['cell_height'], $level, $show['image'], $withBarcode),
            'cells'       => $this->getCells($sheet),
            'cellWidth'   => $sheet['cell_width'],
            'cellHeight'  => $sheet['cell_height'],
            'cellPadding' => self::CELL_PADDING,
            'cellTopPadding' => self::TOP_PADDING,
            'cutGuides'   => filter_var($options['cut_guides'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ], [], [
            'title'         => $filename,
            'format'        => 'A4',
            'orientation'   => 'P',
            'margin_left'   => 0,
            'margin_right'  => 0,
            'margin_top'    => 0,
            'margin_bottom' => 0,
            'margin_header' => 0,
            'margin_footer' => 0,
        ]);
    }

    /**
     * @return array<int, array{left: float, top: float}>
     */
    private function getCells(array $sheet): array
    {
        $leftMargin = ($sheet['page_width'] - $sheet['columns'] * $sheet['cell_width'] - ($sheet['columns'] - 1) * $sheet['column_gap']) / 2;
        $topMargin  = ($sheet['page_height'] - $sheet['rows'] * $sheet['cell_height'] - ($sheet['rows'] - 1) * $sheet['row_gap']) / 2;

        $cells = [];

        for ($row = 0; $row < $sheet['rows']; $row++) {
            for ($column = 0; $column < $sheet['columns']; $column++) {
                $cells[] = [
                    'left' => $leftMargin + $column * ($sheet['cell_width'] + $sheet['column_gap']),
                    'top'  => $topMargin + $row * ($sheet['cell_height'] + $sheet['row_gap']),
                ];
            }
        }

        return $cells;
    }

    /**
     * A field is printed only when it was asked for and the org stock actually has it, so a tick
     * left on for a value that was since cleared prints a shorter label instead of an empty line.
     *
     * @return array<string, bool>
     */
    private function getVisibleFields(array $options, array $label, string $level): array
    {
        $allowed = self::LEVEL_FIELDS[$level];

        $has = [
            'with_image'             => filled($label['image_path']),
            'with_made_in'           => filled($label['made_in']),
            'with_manufactured_by'   => filled($label['manufactured_by']),
            'with_weight'            => filled($label['weight']),
            'with_custom_text'       => filled($this->getCustomText($options)),
            'with_account_signature' => filled($label['signature']),
        ];

        $show = [];

        foreach ($has as $key => $hasValue) {
            $show[$key] = in_array($key, $allowed, true) && $hasValue && $this->wants($options, $key);
        }

        return [
            'image'           => $show['with_image'],
            'made_in'         => $show['with_made_in'],
            'manufactured_by' => $show['with_manufactured_by'],
            'weight'          => $show['with_weight'],
            'custom_text'     => $show['with_custom_text'],
            'signature'       => $show['with_account_signature'],
        ];
    }

    /**
     * Opening the label without saying what to put on it prints what Aurora printed by default,
     * which is everything except the signature.
     */
    private function wants(array $options, string $key): bool
    {
        if (!array_key_exists($key, $options)) {
            return $key !== 'with_account_signature';
        }

        return filter_var($options[$key], FILTER_VALIDATE_BOOLEAN);
    }

    private function getCustomText(array $options): ?string
    {
        $customText = trim((string) ($options['custom_text'] ?? ''));

        return $customText === '' ? null : $customText;
    }

    /**
     * @return array{width: float, height: float}
     */
    private function getSize(array $options, string $level): array
    {
        $size = $options['size'] ?? null;

        if (is_string($size) && in_array($size, self::LEVEL_SIZES[$level], true)) {
            return self::SIZES[$size];
        }

        return self::SIZES[self::DEFAULT_SIZE];
    }

    /**
     * Type is scaled off the label itself rather than listed per stock, so the seven Aurora sizes
     * and the sheet cell all read the same way and a size added later needs no new branch.
     *
     * The driver is the label's height, close to linearly, because height is what decides how many
     * lines of the signature block fit: scaling on area instead left a fully ticked 140 x 90 label
     * with its text in the top half and a band of blank paper under it. The smallest stock sets the
     * base, since 63.5 x 29.6 is the one where the header, the signature and the weight only just
     * fit, and the signature is set smaller again, as Aurora set it.
     *
     * The label is split into columns here rather than in the template, because how wide the text
     * may run depends on what is actually being printed beside it: three columns when there is a
     * picture, two when there is not, and the barcode and picture are then drawn to fill the column
     * they were given instead of keeping a size the label has outgrown.
     *
     * @return array<string, float|int|string>
     */
    private function getScale(float $width, float $height, string $level = 'unit', bool $withImage = false, bool $withBarcode = true): array
    {
        $factor = min(max($height / 29.6, 1.0), 3.05);

        /* The SKO label carries three short lines instead of a dozen, and is read off a pallet
           rather than in the hand, so its code is set several times larger and its picture is given
           the room the signature block would have taken on a unit label. */
        if ($level === 'sko') {
            /* Four parts of six to the wording and two to the picture, with the barcode run across
               the full width underneath both, which is how Aurora lays the outer packing label out. */
            $imageWidth = $withImage ? 100 / 3 : 0.0;
            $imageMm    = $width * $imageWidth / 100;

            return [
                'code'               => round(9.0 * $factor, 2),
                'name'               => round(5.0 * $factor, 2),
                'text_width'         => round(100 - $imageWidth, 2),
                'image_width'        => round($imageWidth, 2),
                'image'              => round(min($imageMm * 0.92, $height * 0.62) * 3.78).'px',
                'gap'                => round(0.7 * $factor, 2),
                'code_padding_y'     => round(max(0.8 * $factor, 1.0), 2),
                'code_padding_x'     => round(max(2.0 * $factor, 1.5), 2),
            ];
        }

        [$textWidth, $barcodeWidth, $imageWidth] = match (true) {
            $withImage && $withBarcode => [40.0, 32.0, 28.0],
            $withBarcode               => [55.0, 45.0, 0.0],
            $withImage                 => [70.0, 0.0, 30.0],
            default                    => [100.0, 0.0, 0.0],
        };

        $barcodeMm = $width * $barcodeWidth / 100;
        $imageMm   = $width * $imageWidth / 100;

        return [
            'code'           => round(5.0 * $factor, 2),
            'name'           => round(4.2 * $factor, 2),
            'body'           => round(3.6 * $factor, 2),
            'signature'      => round(3.2 * $factor, 2),
            'text_width'     => $textWidth,
            'barcode_width'  => $barcodeWidth,
            'image_width'    => $imageWidth,
            'barcode'        => round(min(max($barcodeMm * 0.85 / 39, 0.30), 1.15), 2),
            'barcode_height' => round(min(max($height * 0.018, 0.70), 1.70), 2),
            'image'          => round(min($imageMm * 0.92, $height * 0.55) * 3.78).'px',
            'gap'            => round(0.5 * $factor, 2),
            'line_gap'       => round(0.30 * ($factor - 1) + 0.15, 2),
        ];
    }

    /**
     * The SKO barcode runs the full width of the label, so it is drawn as a raster image at an
     * exact size in millimetres.
     *
     * A picture rather than mPDF's barcode tag, and a PNG rather than an SVG, because mPDF reserves
     * no row height for either the tag or an SVG: the number underneath ended up printed across the
     * bars. It measures a raster image correctly, which is what keeps the two apart.
     *
     * @param  array<string, mixed>  $label
     * @return array{uri: string, width: string, height: string}|null
     */
    private function getSkoBarcode(array $label, string $level, float $width, float $height): ?array
    {
        $number = $label['barcode']['number'] ?? null;

        if ($level !== 'sko' || blank($number)) {
            return null;
        }

        $type = $label['barcode']['type'] === 'EAN13'
            ? BarcodeGenerator::TYPE_EAN_13
            : BarcodeGenerator::TYPE_CODE_128;

        try {
            $png = (new BarcodeGeneratorPNG())->getBarcode($number, $type, 3, 60);
        } catch (Throwable) {
            return null;
        }

        return [
            'uri'    => 'data:image/png;base64,'.base64_encode($png),
            'width'  => round(($width - 2 * self::CELL_PADDING) * 3.78).'px',
            'height' => round(min($height * 0.26, 20.0) * 3.78).'px',
        ];
    }

    public function rules(): array
    {
        return [
            'level'                  => ['sometimes', 'string', 'in:sko,unit'],
            'layout'                 => ['sometimes', 'string', 'in:single,sheet'],
            'size'                   => ['sometimes', 'string', 'in:'.implode(',', array_keys(self::SIZES))],
            'with_image'             => ['sometimes', 'boolean'],
            'with_made_in'           => ['sometimes', 'boolean'],
            'with_manufactured_by'   => ['sometimes', 'boolean'],
            'with_weight'            => ['sometimes', 'boolean'],
            'with_custom_text'       => ['sometimes', 'boolean'],
            'with_account_signature' => ['sometimes', 'boolean'],
            'cut_guides'             => ['sometimes', 'boolean'],
            'custom_text'            => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @throws \Mpdf\MpdfException
     */
    public function asController(Organisation $organisation, Warehouse $warehouse, OrgStock $orgStock, ActionRequest $request): Response
    {
        $request->validate($this->rules());

        return $this->handle($orgStock, $request->input('level', 'unit'), $request->all());
    }
}
