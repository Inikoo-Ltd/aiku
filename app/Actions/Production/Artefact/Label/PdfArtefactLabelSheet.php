<?php

/*
 * Author: Vika Aqordi <aqordeon@gmail.com>
 * Created: Tue, 09 Sep 2026, Bali, Indonesia
 * Copyright (c) 2026, Inikoo LTD
 */

namespace App\Actions\Production\Artefact\Label;

use App\Actions\OrgAction;
use App\Models\Inventory\OrgStock;
use App\Models\Production\Artefact;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as PDF;
use Mpdf\Mpdf;
use Picqer\Barcode\BarcodeGenerator;
use Picqer\Barcode\BarcodeGeneratorSVG;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PdfArtefactLabelSheet extends OrgAction
{
    use WithArtefactLabelLayout;
    use WithArtefactLabelAuthorisation;

    private const LINE_HEIGHT = 1.1;

    private const TEXT_BOX_HEADROOM = 2.0;

    private const BARCODE_TYPES = [
        'ean13'   => BarcodeGenerator::TYPE_EAN_13,
        'code128' => BarcodeGenerator::TYPE_CODE_128,
    ];

    /**
     * The bars are drawn this many user units wide before being stretched onto the box the designer
     * drew, which keeps every module a whole number of units apart and the ratios exact.
     */
    private const BARCODE_UNIT_WIDTH = 2.0;

    private const BARCODE_UNIT_HEIGHT = 30.0;

    private const PAGE_SIZES = [
        'portrait'  => ['width' => 210.0, 'height' => 297.0],
        'landscape' => ['width' => 297.0, 'height' => 210.0],
    ];

    /**
     * @param  array{path: string, mime_type: string|null}|null  $artwork
     *
     * @throws \Mpdf\MpdfException
     */
    public function handle(Artefact|OrgStock $model, array $modelData, ?array $artwork): Response
    {
        $orientation    = $modelData['orientation'];
        $columns        = (int) $modelData['columns'];
        $rows           = (int) $modelData['rows'];
        $pageMargin     = (float) $modelData['page_margin'];
        $gap            = (float) $modelData['gap'];
        $canvasRotation = (int) ($modelData['canvas_rotation'] ?? 0);

        $page        = self::PAGE_SIZES[$orientation];
        $labelWidth  = ($page['width'] - 2 * $pageMargin - $gap * ($columns - 1)) / $columns;
        $labelHeight = ($page['height'] - 2 * $pageMargin - $gap * ($rows - 1)) / $rows;

        if ($labelWidth <= 0 || $labelHeight <= 0) {
            abort(422, __('The grid does not fit on the page, reduce the number of labels, the margin or the gap.'));
        }

        $filename = 'labels-'.$model->code.'-'.now()->format('Y-m-d').'.pdf';
        $cells    = $this->getCells($columns, $rows, $pageMargin, $gap, $labelWidth, $labelHeight);
        $isVector = Arr::get($artwork, 'mime_type') === 'application/pdf';

        $pdf  = PDF::getPdf([
            'title'         => $filename,
            'format'        => 'A4',
            'orientation'   => $orientation === 'landscape' ? 'L' : 'P',
            'margin_left'   => 0,
            'margin_right'  => 0,
            'margin_top'    => 0,
            'margin_bottom' => 0,
            'margin_header' => 0,
            'margin_footer' => 0,
        ]);
        $mpdf = $pdf->getMpdf();
        $mpdf->AddPage();

        $readableArtwork = $isVector ? $this->getReadableArtwork($artwork['path']) : null;

        try {
            if ($readableArtwork) {
                $this->drawVectorArtwork($mpdf, $readableArtwork['path'], $cells, $labelWidth, $labelHeight, $canvasRotation);
            }

            $mpdf->WriteHTML(view('labels.templates.pdf.artefact_sheet', [
                'cells'         => $cells,
                'fields'        => $this->getFields($modelData['fields'] ?? [], $labelWidth, $labelHeight, max($page['width'], $page['height'])),
                'labelWidth'    => $labelWidth,
                'labelHeight'   => $labelHeight,
                'imageSource'   => $isVector ? null : Arr::get($artwork, 'path'),
                'imageRotation' => $this->getMpdfRotation($canvasRotation),
                'cutGuides'     => (bool) ($modelData['cut_guides'] ?? false),
            ])->render());

            $output = $pdf->output();
        } finally {
            if (($readableArtwork['is_temporary'] ?? false) && is_file($readableArtwork['path'])) {
                unlink($readableArtwork['path']);
            }
        }

        return response($output, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="'.$filename.'"');
    }

    /**
     * @return array{path: string, is_temporary: bool}
     */
    private function getReadableArtwork(string $path): array
    {
        $readableArtwork = MakeArtworkPdfReadable::run($path);

        if (!$readableArtwork) {
            abort(422, __('The PDF artwork could not be read even after converting it, save it again as a PDF 1.4 (Acrobat 5 compatible) file.'));
        }

        return $readableArtwork;
    }

    /**
     * FPDI copies the artwork page in as a form XObject, so its own text and vectors survive into
     * the sheet and stay selectable, which stamping it as a picture would destroy.
     *
     * @param  array<int, array{left: float, top: float}>  $cells
     *
     * @throws \Mpdf\MpdfException
     */
    private function drawVectorArtwork(Mpdf $mpdf, string $path, array $cells, float $labelWidth, float $labelHeight, int $rotation): void
    {
        try {
            $mpdf->setSourceFile($path);
            $template = $mpdf->importPage(1);
        } catch (Throwable) {
            abort(422, __('The PDF artwork could not be read even after converting it, save it again as a PDF 1.4 (Acrobat 5 compatible) file.'));
        }

        $runsSideways = $rotation === 90 || $rotation === 270;
        $boxWidth     = $runsSideways ? $labelHeight : $labelWidth;
        $boxHeight    = $runsSideways ? $labelWidth : $labelHeight;

        foreach ($cells as $cell) {
            $centreX = $cell['left'] + $labelWidth / 2;
            $centreY = $cell['top'] + $labelHeight / 2;

            $mpdf->StartTransform();
            $mpdf->transformRotate($rotation, $centreX, $centreY);
            $mpdf->useTemplate($template, $centreX - $boxWidth / 2, $centreY - $boxHeight / 2, $boxWidth, $boxHeight);
            $mpdf->StopTransform();
        }
    }

    /**
     * @return array<int, array{left: float, top: float}>
     */
    private function getCells(int $columns, int $rows, float $pageMargin, float $gap, float $labelWidth, float $labelHeight): array
    {
        $cells = [];

        for ($row = 0; $row < $rows; $row++) {
            for ($column = 0; $column < $columns; $column++) {
                $cells[] = [
                    'left' => $pageMargin + $column * ($labelWidth + $gap),
                    'top'  => $pageMargin + $row * ($labelHeight + $gap),
                ];
            }
        }

        return $cells;
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<int, array{text: string, left: float, top: float, width: float, height: float, font_size: float, color: string, background_color: string|null, weight: string, rotation: int, barcode: array{uri: string, width: float, height: float, show_value: bool}|null}>
     */
    private function getFields(array $fields, float $labelWidth, float $labelHeight, float $longestPageSide): array
    {
        $placedFields = [];

        foreach ($fields as $field) {
            $text = trim((string) ($field['text'] ?? ''));

            if ($text === '') {
                continue;
            }

            $fontSize   = (float) ($field['font_size'] ?? 8);
            $rotation   = (int) ($field['rotation'] ?? 0);
            $lineHeight = $fontSize * self::LINE_HEIGHT * 25.4 / 72;
            $isBarcode  = ($field['source'] ?? null) === 'barcode';
            $barcode    = $isBarcode ? $this->getBarcode($field, $text, $labelWidth, $labelHeight) : null;
            $textLength = $barcode
                ? $barcode['width']
                : (float) ($field['length'] ?? max($labelWidth - (float) $field['x'] * $labelWidth, 1));
            $boxWidth   = $barcode
                ? $barcode['width']
                : min($textLength + self::TEXT_BOX_HEADROOM, $longestPageSide);
            $blockHeight = $barcode ? $barcode['height'] + ($barcode['show_value'] ? $lineHeight : 0) : $lineHeight;

            [$left, $top] = $this->getRotatedOrigin(
                $rotation,
                (float) $field['x'] * $labelWidth,
                (float) $field['y'] * $labelHeight,
                $textLength,
                $boxWidth,
                $blockHeight
            );

            $placedFields[] = [
                'text'      => $text,
                'left'      => $left,
                'top'       => $top,
                'width'     => $boxWidth,
                'height'    => $blockHeight,
                'font_size' => $fontSize,
                'barcode'   => $barcode,
                'color'     => $field['color'] ?? '#000000',
                'background_color' => $this->getBackgroundColor($field['background_color'] ?? null),
                'weight'    => filter_var($field['bold'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 'bold' : 'normal',
                'rotation'  => $this->getMpdfRotation($rotation),
            ];
        }

        return $placedFields;
    }

    /**
     * The generator pads and re-checksums whatever it is given, which would print bars that read
     * back as a different number than the digits underneath them, so the code is checked first.
     */
    private function isValidEan13(string $text): bool
    {
        if (!preg_match('/^\d{13}$/', $text)) {
            return false;
        }

        $digits = str_split($text);
        $check  = (int) array_pop($digits);
        $sum    = 0;

        foreach ($digits as $index => $digit) {
            $sum += (int) $digit * ($index % 2 === 0 ? 1 : 3);
        }

        return (10 - $sum % 10) % 10 === $check;
    }

    /**
     * The bars are handed over as a vector SVG so mPDF copies the paths straight into the sheet,
     * which keeps them sharp at any print size, which is what a scanner needs.
     *
     * @param  array<string, mixed>  $field
     * @return array{uri: string, width: float, height: float, show_value: bool}
     */
    private function getBarcode(array $field, string $text, float $labelWidth, float $labelHeight): array
    {
        $symbology = $field['barcode_type'] ?? 'code128';
        $type      = self::BARCODE_TYPES[$symbology] ?? BarcodeGenerator::TYPE_CODE_128;
        $width     = max((float) ($field['barcode_width'] ?? 0.6) * $labelWidth, 1);
        $height    = max((float) ($field['barcode_height'] ?? 0.3) * $labelHeight, 1);

        if ($symbology === 'ean13' && !$this->isValidEan13($text)) {
            abort(422, __(':text is not an EAN13, it needs 13 digits ending in the right check digit, print it as a CODE 128 instead.', [
                'text' => $text,
            ]));
        }

        try {
            $svg = (new BarcodeGeneratorSVG())->getBarcode(
                $text,
                $type,
                self::BARCODE_UNIT_WIDTH,
                self::BARCODE_UNIT_HEIGHT,
                $field['color'] ?? '#000000'
            );
        } catch (Throwable) {
            abort(422, __(':text cannot be printed as a :type barcode, correct it or pick the other symbology.', [
                'text' => $text,
                'type' => strtoupper((string) ($field['barcode_type'] ?? 'code128')),
            ]));
        }

        return [
            'uri'        => 'data:image/svg+xml;base64,'.base64_encode($svg),
            'width'      => $width,
            'height'     => $height,
            'show_value' => filter_var($field['barcode_show_value'] ?? true, FILTER_VALIDATE_BOOLEAN),
        ];
    }

    private function getBackgroundColor(mixed $backgroundColor): ?string
    {
        return is_string($backgroundColor) && preg_match('/^#[0-9a-fA-F]{6}$/', $backgroundColor)
            ? $backgroundColor
            : null;
    }

    /**
     * mPDF turns a block after laying it out and keeps a different corner of it still for each
     * angle, so the origin it is given has to be moved back by the size of the box it was laid out
     * in. What comes out is a block whose top left corner sits on the spot the designer dropped it.
     *
     * @return array{0: float, 1: float}
     */
    private function getRotatedOrigin(int $rotation, float $left, float $top, float $textLength, float $boxWidth, float $lineHeight): array
    {
        return match ($rotation) {
            90      => [$left, $top - $lineHeight],
            180     => [$left + $textLength - $boxWidth, $top],
            270     => [$left - $boxWidth + $lineHeight, $top + $textLength],
            default => [$left, $top],
        };
    }

    /**
     * mPDF turns blocks and images by 90, -90 or 180 only.
     */
    private function getMpdfRotation(int $rotation): int
    {
        return match ($rotation) {
            90      => 90,
            180     => 180,
            270     => -90,
            default => 0,
        };
    }

    public function rules(): array
    {
        return array_merge(
            [
                'background_artwork' => $this->artworkFileRules(),
                'artefact_label_id'  => ['sometimes', 'nullable', 'integer'],
            ],
            $this->labelLayoutRules()
        );
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        if (!isset($this->production)) {
            return $this->canViewLabels($request);
        }

        return $request->user()->authTo([
            'org-supervisor.'.$this->organisation->id,
            'productions-view.'.$this->organisation->id,
            "productions_operations.{$this->production->id}.view",
            "productions_operations.{$this->production->id}.orchestrate",
            "productions_rd.{$this->production->id}.view",
        ]);
    }

    /**
     * @throws \Mpdf\MpdfException
     */
    public function asController(Artefact $artefact, ActionRequest $request): Response
    {
        $this->initialisationFromProduction($artefact->production, $request);

        return $this->handle(
            $artefact,
            $this->validatedData,
            $this->getArtwork($artefact, $request->file('background_artwork'), $this->validatedData)
        );
    }

    /**
     * @throws \Mpdf\MpdfException
     */
    public function inOrgStock(OrgStock $orgStock, ActionRequest $request): Response
    {
        $this->initialisation($orgStock->organisation, $request);

        return $this->handle(
            $orgStock,
            $this->validatedData,
            $this->getArtwork($orgStock, $request->file('background_artwork'), $this->validatedData)
        );
    }

    /**
     * A freshly uploaded artwork wins, otherwise a saved label prints against the artwork it was
     * designed with, which is the whole reason that file is kept.
     *
     * @param  array<string, mixed>  $modelData
     * @return array{path: string, mime_type: string|null}|null
     */
    private function getArtwork(Artefact|OrgStock $model, ?UploadedFile $uploaded, array $modelData): ?array
    {
        if ($uploaded) {
            return [
                'path'      => $uploaded->getRealPath(),
                'mime_type' => $uploaded->getMimeType(),
            ];
        }

        $label = Arr::get($modelData, 'artefact_label_id')
            ? $model->labels()->find(Arr::get($modelData, 'artefact_label_id'))
            : null;

        if (!$label?->artwork) {
            return null;
        }

        return [
            'path'      => $label->artwork->getPath(),
            'mime_type' => $label->artwork->mime_type,
        ];
    }
}
