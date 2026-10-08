<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 16 Jul 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Inventory\OrgStock\UI;

use App\Models\Inventory\OrgStock;
use App\Models\SupplyChain\SupplierProduct;
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
     * still prints straight out of this one. 105 x 37 is the exception: it is cut by hand from plain
     * A5 by those who found the 125 x 37 label too wide.
     */
    public const SIZES = [
        '63x29.6'   => ['width' => 63.0, 'height' => 29.6],
        '63.5x29.6' => ['width' => 63.5, 'height' => 29.6],
        '70x29.7'   => ['width' => 70.0, 'height' => 29.7],
        '70x30'     => ['width' => 70.0, 'height' => 30.0],
        '125x37'    => ['width' => 125.0, 'height' => 37.0],
        '105x37'    => ['width' => 105.0, 'height' => 37.0],
        '130x60'    => ['width' => 130.0, 'height' => 60.0],
        '140x90'    => ['width' => 140.0, 'height' => 90.0],
        '97x69'     => ['width' => 97.0, 'height' => 69.0],
        '105x74.25' => ['width' => 105.0, 'height' => 74.25],
    ];

    /**
     * The outer packing label carries far less than the unit one, so Aurora only ever offered it on
     * the four stocks that suit a box, and those are the only ones offered here.
     */
    public const LEVEL_SIZES = [
        'unit' => ['63x29.6', '63.5x29.6', '70x29.7', '70x30', '125x37', '105x37', '130x60', '140x90'],
        'sko'    => ['63x29.6', '63.5x29.6', '70x29.7', '130x60'],
        'carton' => ['97x69', '105x74.25'],
    ];

    public const DEFAULT_SIZE = '63x29.6';

    /**
     * Only the unit label names the goods to whoever ends up holding them, so only it carries the
     * origin, the manufacturer, the weight and the account signature. The SKO label is a box label.
     */
    public const LEVEL_FIELDS = [
        'unit' => ['with_image', 'with_made_in', 'with_manufactured_by', 'with_weight', 'with_custom_text', 'with_account_signature'],
        'sko'    => ['with_image', 'with_custom_text'],
        'carton' => ['with_image', 'with_ingredients', 'with_custom_text'],
    ];

    /**
     * The A4 sheet each label size is die cut on, copied from Aurora's labels data so a box of sheets
     * bought for the old system prints straight out of this one. Each size has exactly one sheet, so
     * picking the size is what picks the sheet. EU30161 keeps the 2.5mm column gap and centred
     * margins it was first measured with here.
     */
    public const SHEETS = [
        '63x29.6'   => [
            'code'        => 'EU30161',
            'columns'     => 3,
            'rows'        => 9,
            'cell_width'  => 63.5,
            'cell_height' => 29.6,
            'margin_top'  => 15.3,
            'margin_left' => 7.25,
            'column_gap'  => 2.5,
            'row_gap'     => 0.0,
            'orientation' => 'P',
        ],
        '63.5x29.6' => [
            'code'        => 'SK06302900',
            'columns'     => 3,
            'rows'        => 9,
            'cell_width'  => 63.5,
            'cell_height' => 29.6,
            'margin_top'  => 15.3,
            'margin_left' => 9.75,
            'column_gap'  => 0.0,
            'row_gap'     => 0.0,
            'orientation' => 'P',
        ],
        '70x29.7'   => [
            'code'        => 'EU30040',
            'columns'     => 3,
            'rows'        => 10,
            'cell_width'  => 70.0,
            'cell_height' => 29.7,
            'margin_top'  => 0.0,
            'margin_left' => 0.0,
            'column_gap'  => 0.0,
            'row_gap'     => 0.0,
            'orientation' => 'P',
        ],
        '70x30'     => [
            'code'        => 'ES0027D',
            'columns'     => 3,
            'rows'        => 9,
            'cell_width'  => 70.0,
            'cell_height' => 30.0,
            'margin_top'  => 13.5,
            'margin_left' => 0.0,
            'column_gap'  => 0.0,
            'row_gap'     => 0.0,
            'orientation' => 'P',
        ],
        '125x37'    => [
            'code'        => 'EU30140',
            'columns'     => 1,
            'rows'        => 7,
            'cell_width'  => 125.0,
            'cell_height' => 37.0,
            'margin_top'  => 10.0,
            'margin_left' => 42.5,
            'column_gap'  => 0.0,
            'row_gap'     => 3.0,
            'orientation' => 'P',
        ],
        '105x37'    => [
            'code'        => 'A5-105x37',
            'paper'       => 'A5',
            'columns'     => 1,
            'rows'        => 5,
            'cell_width'  => 105.0,
            'cell_height' => 37.0,
            'margin_top'  => 6.5,
            'margin_left' => 21.5,
            'column_gap'  => 0.0,
            'row_gap'     => 3.0,
            'orientation' => 'P',
        ],
        '130x60'    => [
            'code'        => 'EU30137',
            'columns'     => 2,
            'rows'        => 3,
            'cell_width'  => 130.0,
            'cell_height' => 60.0,
            'margin_top'  => 10.0,
            'margin_left' => 16.0,
            'column_gap'  => 5.0,
            'row_gap'     => 5.0,
            'orientation' => 'L',
        ],
        '140x90'    => [
            'code'        => 'EU30129',
            'columns'     => 1,
            'rows'        => 3,
            'cell_width'  => 140.0,
            'cell_height' => 90.0,
            'margin_top'  => 11.5,
            'margin_left' => 35.0,
            'column_gap'  => 0.0,
            'row_gap'     => 2.0,
            'orientation' => 'P',
        ],
        '97x69'     => [
            'code'        => 'EU30090',
            'columns'     => 2,
            'rows'        => 4,
            'cell_width'  => 97.0,
            'cell_height' => 69.0,
            'margin_top'  => 6.0,
            'margin_left' => 6.5,
            'column_gap'  => 3.0,
            'row_gap'     => 3.0,
            'orientation' => 'P',
        ],
        '105x74.25' => [
            'code'        => 'EU30036',
            'columns'     => 2,
            'rows'        => 4,
            'cell_width'  => 105.0,
            'cell_height' => 74.25,
            'margin_top'  => 0.0,
            'margin_left' => 0.0,
            'column_gap'  => 0.0,
            'row_gap'     => 0.0,
            'orientation' => 'P',
        ],
    ];

    private const CELL_PADDING = 1.5;

    /**
     * The top is trimmed closer than the other three sides. A label is read from its code down, so
     * the code wants to sit against the top edge, and the glyph's own ascent already contributes
     * about a millimetre of apparent space above it before any margin is added.
     */
    private const TOP_PADDING = 0.8;

    private const WIDE_RATIO = 2.75;

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
        $label = GetOrgStockLabelData::run($orgStock, $level, $this->getSupplierProduct($orgStock, $options));

        /* The unit label is built around its barcode, so without one there is nothing to print. The
           SKO label never carries one, and prints for a box that has not been given a number yet. */
        if ($level === 'unit' && blank($label['barcode']['number'])) {
            abort(404, __('This org stock has no barcode yet'));
        }

        $show     = $this->getVisibleFields($options, $label, $level);
        $isSheet  = ($options['layout'] ?? 'single') === 'sheet';
        $sizeKey  = $this->getSizeKey($options, $level);
        $size     = self::SIZES[$sizeKey];
        $filename = 'label-'.$orgStock->code.'-'.$level.($isSheet ? '-'.self::SHEETS[$sizeKey]['code'] : '').'.pdf';

        $withBarcode = filled($label['barcode']['number']);

        $pdf = $isSheet
            ? $this->getSheetPdf(self::SHEETS[$sizeKey], $label, $show, $options, $level, $withBarcode, $filename)
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
            'cartonBarcode' => $this->getCartonBarcode($label, $level, $size['width'], $size['height']),
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
    private function getSheetPdf(array $sheet, array $label, array $show, array $options, string $level, bool $withBarcode, string $filename)
    {
        return PDF::loadView('labels.templates.pdf.org_stock.label_sheet', [
            'label'       => $label,
            'show'        => $show,
            'level'       => $level,
            'skoBarcode'  => $this->getSkoBarcode($label, $level, $sheet['cell_width'], $sheet['cell_height']),
            'cartonBarcode' => $this->getCartonBarcode($label, $level, $sheet['cell_width'], $sheet['cell_height']),
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
            'format'        => $sheet['paper'] ?? 'A4',
            'orientation'   => $sheet['orientation'],
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
        $cells = [];

        for ($row = 0; $row < $sheet['rows']; $row++) {
            for ($column = 0; $column < $sheet['columns']; $column++) {
                $cells[] = [
                    'left' => $sheet['margin_left'] + $column * ($sheet['cell_width'] + $sheet['column_gap']),
                    'top'  => $sheet['margin_top'] + $row * ($sheet['cell_height'] + $sheet['row_gap']),
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
            'with_ingredients'       => filled($label['materials']),
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
            'materials'       => $show['with_ingredients'],
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

    private function getSizeKey(array $options, string $level): string
    {
        $size = $options['size'] ?? null;

        if (is_string($size) && in_array($size, self::LEVEL_SIZES[$level], true)) {
            return $size;
        }

        return in_array(self::DEFAULT_SIZE, self::LEVEL_SIZES[$level], true) ? self::DEFAULT_SIZE : self::LEVEL_SIZES[$level][0];
    }

    /**
     * A stock can be bought from more than one supplier, each with its own pictures and carton, so
     * the page the label was asked from names the supplier product.
     */
    private function getSupplierProduct(OrgStock $orgStock, array $options): ?SupplierProduct
    {
        $supplierProductId = $options['supplier_product'] ?? null;

        return blank($supplierProductId) ? null : GetOrgStockLabelData::make()->getSupplierProduct($orgStock, (int) $supplierProductId);
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

        if ($level === 'carton') {
            $cartonFactor = $height / 69;
            $imageWidth   = $withImage ? 25.0 : 0.0;

            return [
                'header'      => round(4.25 * $cartonFactor, 2),
                'caption'     => round(5.67 * $cartonFactor, 2),
                'value'       => round(8.5 * $cartonFactor, 2),
                'small'       => round(7.0 * $cartonFactor, 2),
                'text_width'  => 100 - $imageWidth,
                'image_width' => $imageWidth,
                'image'       => round(min($width * $imageWidth / 100 * 0.9, $height * 0.45) * 3.78).'px',
            ];
        }

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

        if ($width / $height >= self::WIDE_RATIO) {
            return $this->getWideUnitScale($width, $height, $factor, $withImage, $withBarcode);
        }

        [$textWidth, $barcodeWidth, $imageWidth] = match (true) {
            $withImage && $withBarcode => [40.0, 32.0, 28.0],
            $withBarcode               => [55.0, 45.0, 0.0],
            $withImage                 => [70.0, 0.0, 30.0],
            default                    => [100.0, 0.0, 0.0],
        };

        $barcodeMm = $width * $barcodeWidth / 100;
        $imageMm   = min($width * $imageWidth / 100 * 0.92, $height * 0.55);

        /* On a long, low stock such as 125 x 37 the picture is held back by the height, not by its
           column, and the unused column opened a wide gap between the barcode and the picture. The
           column is trimmed to the picture and what is left goes to the wording. */
        if ($withImage) {
            $fittedImageWidth = round($imageMm / 0.92 / $width * 100, 2);
            $textWidth        = round($textWidth + $imageWidth - $fittedImageWidth, 2);
            $imageWidth       = $fittedImageWidth;
        }

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
            'image'          => round($imageMm * 3.78).'px',
            'gap'            => round(0.5 * $factor, 2),
            'line_gap'       => round(0.30 * ($factor - 1) + 0.15, 2),
        ];
    }

    /**
     * A long, low unit label such as 125 x 37 is laid out as Aurora printed it: the wording runs
     * down the left with the rule under the name only, a small barcode stands in the middle, and
     * the picture takes the full height of the label on the right.
     *
     * @return array<string, float|int|string>
     */
    private function getWideUnitScale(float $width, float $height, float $factor, bool $withImage, bool $withBarcode): array
    {
        $innerHeight = $height - self::TOP_PADDING - self::CELL_PADDING;
        $imageMm     = $withImage ? $innerHeight * 0.9 : 0.0;
        $imageWidth  = $withImage ? round(($imageMm + 2.0) / $width * 100, 2) : 0.0;

        $barcodeWidth = $withBarcode ? round(22.5 / $width * 100, 2) : 0.0;
        $barcodeSize  = round(22.5 * 0.82 / self::EAN13_WIDTH_MM, 2);
        $barcodeMm    = $height * 0.42;

        return [
            'layout'         => 'wide',
            'code'           => round(7.6 * $factor, 2),
            'name'           => round(6.4 * $factor, 2),
            'body'           => round(4.2 * $factor, 2),
            'signature'      => round(4.0 * $factor, 2),
            'text_width'     => round(100 - $barcodeWidth - $imageWidth, 2),
            'barcode_width'  => $barcodeWidth,
            'image_width'    => $imageWidth,
            'barcode'        => $barcodeSize,
            'barcode_height' => $withBarcode ? round($barcodeMm / (self::EAN13_HEIGHT_MM * $barcodeSize), 2) : 0.0,
            'image'          => round($imageMm * 3.78).'px',
            'gap'            => round(0.6 * $factor, 2),
            'line_gap'       => round(0.25 * $factor, 2),
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

    /**
     * The carton barcode is printed as CODE 128 whatever its digits, as Aurora printed it, and runs
     * across most of the label rather than the full width, leaving the quiet zones clear.
     *
     * @param  array<string, mixed>  $label
     * @return array{uri: string, width: string, height: string}|null
     */
    private function getCartonBarcode(array $label, string $level, float $width, float $height): ?array
    {
        $number = $label['barcode']['number'] ?? null;

        if ($level !== 'carton' || blank($number)) {
            return null;
        }

        try {
            $png = (new BarcodeGeneratorPNG())->getBarcode($number, BarcodeGenerator::TYPE_CODE_128, 3, 60);
        } catch (Throwable) {
            return null;
        }

        return [
            'uri'    => 'data:image/png;base64,'.base64_encode($png),
            'width'  => round(($width - 2 * self::CELL_PADDING) * 0.6 * 3.78).'px',
            'height' => round(min($height * 0.16, 14.0) * 3.78).'px',
        ];
    }

    public function rules(): array
    {
        return [
            'level'                  => ['sometimes', 'string', 'in:sko,unit,carton'],
            'supplier_product'       => ['sometimes', 'nullable', 'integer'],
            'with_ingredients'       => ['sometimes', 'boolean'],
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
