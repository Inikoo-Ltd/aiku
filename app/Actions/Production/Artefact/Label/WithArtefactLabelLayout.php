<?php

/*
 * Author: Vika Aqordi <aqordeon@gmail.com>
 * Created: Thu, 10 Sep 2026, Bali, Indonesia
 * Copyright (c) 2026, Inikoo LTD
 */

namespace App\Actions\Production\Artefact\Label;

use App\Actions\Helpers\Media\SaveModelAttachment;
use App\Models\Helpers\Media;
use App\Models\Production\Artefact;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;

trait WithArtefactLabelLayout
{
    private const ARTWORK_SCOPE = 'label_artwork';

    private const ARTWORK_MIME_TYPES = 'image/jpeg,image/png,image/gif,image/webp,application/pdf';

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
            'fields.*.text'      => ['required', 'string', 'max:255'],
            'fields.*.x'         => ['required', 'numeric', 'min:0', 'max:1'],
            'fields.*.y'         => ['required', 'numeric', 'min:0', 'max:1'],
            'fields.*.font_size' => ['required', 'numeric', 'min:3', 'max:72'],
            'fields.*.color'     => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'fields.*.bold'      => ['sometimes', 'boolean'],
            'fields.*.rotation'  => ['sometimes', 'integer', 'in:0,90,180,270'],
            'fields.*.length'    => ['sometimes', 'numeric', 'min:0.1', 'max:1000'],
            'fields.*.source'    => ['sometimes', 'string', 'in:batch_code,expiry_date'],
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
     * left out because the browser works it out again from the font it is about to print with.
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
                    'bold'      => filter_var(Arr::get($field, 'bold', false), FILTER_VALIDATE_BOOLEAN),
                    'rotation'  => (int) Arr::get($field, 'rotation', 0),
                ],
                Arr::get($layout, 'fields', [])
            )),
        ];
    }

    /**
     * The artwork is kept as an attachment of the artefact so a label opened months later still
     * prints the sheet it was designed against, and so the artefact keeps its own photos apart.
     */
    protected function saveArtwork(Artefact $artefact, UploadedFile $file): Media
    {
        return SaveModelAttachment::make()->action($artefact, [
            'path'         => $file->getPathName(),
            'originalName' => $file->getClientOriginalName(),
            'extension'    => $file->guessClientExtension(),
            'scope'        => self::ARTWORK_SCOPE,
            'caption'      => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
        ]);
    }
}
