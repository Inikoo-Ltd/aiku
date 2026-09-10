<?php

/*
 * Author: Vika Aqordi <aqordeon@gmail.com>
 * Created: Tue, 09 Sep 2026, Bali, Indonesia
 * Copyright (c) 2026, Inikoo LTD
 */

namespace App\Actions\Production\Artefact\Label;

use App\Actions\OrgAction;
use App\Models\Production\Artefact;
use Illuminate\Http\UploadedFile;
use Lorisleiva\Actions\ActionRequest;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as PDF;
use Symfony\Component\HttpFoundation\Response;

class PdfArtefactLabelSheet extends OrgAction
{
    private const LINE_HEIGHT = 1.1;

    private const TEXT_BOX_HEADROOM = 2.0;

    private const PAGE_SIZES = [
        'portrait'  => ['width' => 210.0, 'height' => 297.0],
        'landscape' => ['width' => 297.0, 'height' => 210.0],
    ];

    /**
     * @throws \Mpdf\MpdfException
     */
    public function handle(Artefact $artefact, array $modelData, ?UploadedFile $backgroundImage): Response
    {
        $orientation = $modelData['orientation'];
        $columns     = (int) $modelData['columns'];
        $rows        = (int) $modelData['rows'];
        $pageMargin  = (float) $modelData['page_margin'];
        $gap         = (float) $modelData['gap'];

        $page        = self::PAGE_SIZES[$orientation];
        $labelWidth  = ($page['width'] - 2 * $pageMargin - $gap * ($columns - 1)) / $columns;
        $labelHeight = ($page['height'] - 2 * $pageMargin - $gap * ($rows - 1)) / $rows;

        if ($labelWidth <= 0 || $labelHeight <= 0) {
            abort(422, __('The grid does not fit on the page, reduce the number of labels, the margin or the gap.'));
        }

        $filename = 'labels-'.$artefact->code.'-'.now()->format('Y-m-d').'.pdf';

        $pdf = PDF::loadView('labels.templates.pdf.artefact_sheet', [
            'cells'       => $this->getCells($columns, $rows, $pageMargin, $gap, $labelWidth, $labelHeight),
            'fields'      => $this->getFields($modelData['fields'] ?? [], $labelWidth, $labelHeight, max($page['width'], $page['height'])),
            'labelWidth'  => $labelWidth,
            'labelHeight' => $labelHeight,
            'imageSource'   => $backgroundImage?->getRealPath(),
            'imageRotation' => $this->getMpdfRotation((int) ($modelData['canvas_rotation'] ?? 0)),
            'cutGuides'   => (bool) ($modelData['cut_guides'] ?? false),
        ], [], [
            'title'                  => $filename,
            'format'                 => 'A4',
            'orientation'            => $orientation === 'landscape' ? 'L' : 'P',
            'margin_left'            => 0,
            'margin_right'           => 0,
            'margin_top'             => 0,
            'margin_bottom'          => 0,
            'margin_header'          => 0,
            'margin_footer'          => 0,
        ]);

        return response($pdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="'.$filename.'"');
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
     * @return array<int, array{text: string, left: float, top: float, width: float, height: float, font_size: float, color: string, weight: string, rotation: int}>
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
            $textLength = (float) ($field['length'] ?? max($labelWidth - (float) $field['x'] * $labelWidth, 1));
            $boxWidth   = min($textLength + self::TEXT_BOX_HEADROOM, $longestPageSide);

            [$left, $top] = $this->getRotatedOrigin(
                $rotation,
                (float) $field['x'] * $labelWidth,
                (float) $field['y'] * $labelHeight,
                $textLength,
                $boxWidth,
                $lineHeight
            );

            $placedFields[] = [
                'text'      => $text,
                'left'      => $left,
                'top'       => $top,
                'width'     => $boxWidth,
                'height'    => $lineHeight,
                'font_size' => $fontSize,
                'color'     => $field['color'] ?? '#000000',
                'weight'    => filter_var($field['bold'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 'bold' : 'normal',
                'rotation'  => $this->getMpdfRotation($rotation),
            ];
        }

        return $placedFields;
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
        return [
            'orientation'        => ['required', 'in:portrait,landscape'],
            'columns'            => ['required', 'integer', 'min:1', 'max:20'],
            'rows'               => ['required', 'integer', 'min:1', 'max:30'],
            'page_margin'        => ['required', 'numeric', 'min:0', 'max:40'],
            'gap'                => ['required', 'numeric', 'min:0', 'max:30'],
            'cut_guides'         => ['sometimes', 'boolean'],
            'canvas_rotation'    => ['sometimes', 'integer', 'in:0,90,180,270'],
            'background_image'   => ['sometimes', 'nullable', 'image', 'max:8192'],
            'fields'             => ['sometimes', 'array', 'max:100'],
            'fields.*.text'      => ['required', 'string', 'max:255'],
            'fields.*.x'         => ['required', 'numeric', 'min:0', 'max:1'],
            'fields.*.y'         => ['required', 'numeric', 'min:0', 'max:1'],
            'fields.*.font_size' => ['required', 'numeric', 'min:3', 'max:72'],
            'fields.*.color'     => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'fields.*.bold'      => ['sometimes', 'boolean'],
            'fields.*.rotation'  => ['sometimes', 'integer', 'in:0,90,180,270'],
            'fields.*.length'    => ['sometimes', 'numeric', 'min:0.1', 'max:1000'],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
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

        return $this->handle($artefact, $this->validatedData, $request->file('background_image'));
    }
}
