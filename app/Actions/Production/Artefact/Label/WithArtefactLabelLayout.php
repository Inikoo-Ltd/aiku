<?php

/*
 * Author: Vika Aqordi <aqordeon@gmail.com>
 * Created: Thu, 10 Sep 2026, Bali, Indonesia
 * Copyright (c) 2026, Inikoo LTD
 */

namespace App\Actions\Production\Artefact\Label;

use App\Actions\Helpers\Media\SaveModelAttachment;
use App\Models\Helpers\Media;
use App\Enums\Production\Artefact\ArtefactLabelInformationEnum;
use App\Models\Inventory\OrgStock;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;

trait WithArtefactLabelLayout
{
    private const ARTWORK_SCOPE = 'label_artwork';

    private const ARTWORK_MIME_TYPES = 'image/jpeg,image/png,image/gif,image/webp,application/pdf';

    private const DEFAULT_BARCODE_TYPE = 'code128';

    private const DEFAULT_BARCODE_WIDTH = 0.6;

    private const DEFAULT_BARCODE_HEIGHT = 0.3;

    private const DEFAULT_ICON_SIZE = 0.2;

    private const LAYOUT_KEYS = [
        'orientation',
        'columns',
        'rows',
        'page_margin',
        'gap',
        'cut_guides',
        'canvas_rotation',
        'is_sheet_artwork',
        'fields',
    ];

    /**
     * @return array<string, array<int, string>>
     */
    public function labelLayoutRules(): array
    {
        return [
            'orientation'        => ['required', 'in:portrait,landscape'],
            'columns'            => ['required', 'integer', 'min:1', 'max:20'],
            'rows'               => ['required', 'integer', 'min:1', 'max:30'],
            'page_margin'        => ['required', 'numeric', 'min:0', 'max:40'],
            'gap'                => ['required', 'numeric', 'min:0', 'max:30'],
            'cut_guides'         => ['sometimes', 'boolean'],
            'canvas_rotation'    => ['sometimes', 'integer', 'in:0,90,180,270'],
            'is_sheet_artwork'   => ['sometimes', 'boolean'],
            'fields'             => ['sometimes', 'array', 'max:100'],
            'fields.*.text'      => ['required', 'string', 'max:5000'],
            'fields.*.x'         => ['required', 'numeric', 'min:0', 'max:1'],
            'fields.*.y'         => ['required', 'numeric', 'min:0', 'max:1'],
            'fields.*.font_size' => ['required', 'numeric', 'min:3', 'max:72'],
            'fields.*.color'     => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'fields.*.background_color' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'fields.*.bold'      => ['sometimes', 'boolean'],
            'fields.*.rotation'  => ['sometimes', 'integer', 'in:0,90,180,270'],
            'fields.*.length'    => ['sometimes', 'numeric', 'min:0.1', 'max:1000'],
            'fields.*.source'    => ['sometimes', ...ArtefactLabelInformationEnum::sourceRule()],
            'fields.*.box_width' => ['sometimes', 'nullable', 'numeric', 'min:0.02', 'max:1'],
            'fields.*.height'    => ['sometimes', 'numeric', 'min:0.1', 'max:1000'],
            'fields.*.icon_size' => ['sometimes', 'numeric', 'min:0.02', 'max:1'],
            'fields.*.barcode_type'       => ['sometimes', 'string', 'in:ean13,code128'],
            'fields.*.barcode_width'      => ['sometimes', 'numeric', 'min:0.02', 'max:1'],
            'fields.*.barcode_height'     => ['sometimes', 'numeric', 'min:0.02', 'max:1'],
            'fields.*.barcode_show_value' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function artworkFileRules(): array
    {
        return ['sometimes', 'nullable', 'file', 'mimetypes:'.self::ARTWORK_MIME_TYPES, 'max:8192'];
    }

    /**
     * A multipart form sends every value as a string, and a stored "0" reads back as true in the
     * browser, so the layout is typed here before it is written down. The measured text length is
     * left out because the browser works it out again from the font it is about to print with. The
     * measured height is kept: a wrapped text printed by an agent has no browser to measure it.
     *
     * @param  array<string, mixed>  $modelData
     * @return array<string, mixed>
     */
    protected function packLayout(array $modelData): array
    {
        $layout = Arr::only($modelData, self::LAYOUT_KEYS);

        return [
            'orientation'      => Arr::get($layout, 'orientation'),
            'columns'          => (int) Arr::get($layout, 'columns'),
            'rows'             => (int) Arr::get($layout, 'rows'),
            'page_margin'      => (float) Arr::get($layout, 'page_margin'),
            'gap'              => (float) Arr::get($layout, 'gap'),
            'cut_guides'       => filter_var(Arr::get($layout, 'cut_guides', false), FILTER_VALIDATE_BOOLEAN),
            'canvas_rotation'  => (int) Arr::get($layout, 'canvas_rotation', 0),
            'is_sheet_artwork' => filter_var(Arr::get($layout, 'is_sheet_artwork', false), FILTER_VALIDATE_BOOLEAN),
            'fields'           => array_values(array_map(
                fn (array $field) => [
                    'source'    => Arr::get($field, 'source', 'batch_code'),
                    'text'      => Arr::get($field, 'text'),
                    'x'         => (float) Arr::get($field, 'x'),
                    'y'         => (float) Arr::get($field, 'y'),
                    'font_size' => (float) Arr::get($field, 'font_size'),
                    'color'     => Arr::get($field, 'color'),
                    'background_color' => Arr::get($field, 'background_color') ?: null,
                    'bold'      => filter_var(Arr::get($field, 'bold', false), FILTER_VALIDATE_BOOLEAN),
                    'rotation'  => (int) Arr::get($field, 'rotation', 0),
                    'barcode_type'       => Arr::get($field, 'barcode_type', self::DEFAULT_BARCODE_TYPE),
                    'barcode_width'      => (float) Arr::get($field, 'barcode_width', self::DEFAULT_BARCODE_WIDTH),
                    'barcode_height'     => (float) Arr::get($field, 'barcode_height', self::DEFAULT_BARCODE_HEIGHT),
                    'barcode_show_value' => filter_var(Arr::get($field, 'barcode_show_value', true), FILTER_VALIDATE_BOOLEAN),
                    'box_width'          => Arr::get($field, 'box_width') ? (float) Arr::get($field, 'box_width') : null,
                    'height'             => Arr::get($field, 'height') ? (float) Arr::get($field, 'height') : null,
                    'icon_size'          => (float) Arr::get($field, 'icon_size', self::DEFAULT_ICON_SIZE),
                ],
                Arr::get($layout, 'fields', [])
            )),
        ];
    }

    /**
     * The artwork is kept as an attachment of the artefact so a label opened months later still
     * prints the sheet it was designed against, and so the artefact keeps its own photos apart.
     */
    protected function saveArtwork(OrgStock $orgStock, UploadedFile $file): Media
    {
        return SaveModelAttachment::make()->action($orgStock, [
            'path'         => $file->getPathName(),
            'originalName' => $file->getClientOriginalName(),
            'extension'    => $file->guessClientExtension(),
            'scope'        => self::ARTWORK_SCOPE,
            'caption'      => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
        ]);
    }
}
