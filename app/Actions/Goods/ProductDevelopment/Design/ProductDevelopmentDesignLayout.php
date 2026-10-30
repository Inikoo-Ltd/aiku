<?php

/*
 * Author Louis Perez
 * Created on 17-10-2026-10h-20m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment\Design;

use App\Models\Goods\ProductDevelopment;
use App\Models\Goods\ProductDevelopmentItem;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentTargetFieldEnum;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/**
 * The box a design version is drawn on: its size in millimetres and what sits on each of its six faces.
 * Element positions are millimetres from the top-left corner of their face, so a layout keeps its scale
 * when the box is resized.
 */
class ProductDevelopmentDesignLayout
{
    public const array FACES = ['front', 'back', 'left', 'right', 'top', 'bottom'];

    public const string KIND_BOX = 'box';
    public const string KIND_SHEET = 'sheet';

    /**
     * A label is one flat page, so its layout has a single face.
     */
    public const array SHEET_FACES = ['sheet'];

    public const array ELEMENT_TYPES = ['text', 'image', 'zone', 'data', 'pictogram', 'barcode', 'shape'];

    public const array SHAPES = ['rect', 'rounded', 'ellipse', 'triangle', 'line'];

    /**
     * A solid hex colour, or a two-stop gradient written as "linear:<angle>:#from:#to" or "radial:#from:#to".
     */
    public const string PAINT_PATTERN = '/^(#[0-9a-fA-F]{6}|linear:\d{1,3}:#[0-9a-fA-F]{6}:#[0-9a-fA-F]{6}|radial:#[0-9a-fA-F]{6}:#[0-9a-fA-F]{6}|none)$/';

    public const array BARCODE_SOURCES = ['unit', 'sko', 'carton'];

    public const array ZONES = ['compliance', 'barcode', 'other'];

    public const int MAX_ELEMENTS_PER_FACE = 60;

    public const int MAX_SIDE_MM = 2000;

    /**
     * @return array<string, mixed>
     */
    public static function blank(ProductDevelopment $productDevelopment, ?ProductDevelopmentItem $item = null): array
    {
        return [
            'kind'   => self::KIND_BOX,
            'units'  => 'mm',
            'width'  => null,
            'length' => null,
            'height' => null,
            ...self::sizeFromSku($item ?? $productDevelopment->items()->with('tradeUnit')->orderBy('id')->first()),
            'faces'  => self::blankFaces(self::FACES),
        ];
    }

    /**
     * A label page with no size yet: the designer types its width and height.
     *
     * @return array<string, mixed>
     */
    public static function blankSheet(): array
    {
        return [
            'kind'   => self::KIND_SHEET,
            'units'  => 'mm',
            'width'  => null,
            'length' => null,
            'height' => null,
            'faces'  => self::blankFaces(self::SHEET_FACES),
        ];
    }

    /**
     * @param array<int, string> $faces
     *
     * @return array<string, array<string, mixed>>
     */
    private static function blankFaces(array $faces): array
    {
        return collect($faces)->mapWithKeys(fn (string $face) => [$face => ['background' => ['color' => '#ffffff', 'image_file_id' => null, 'x' => 0.5, 'y' => 0.5, 'zoom' => 1], 'elements' => []]])->all();
    }

    /**
     * @param array<string, mixed> $layout
     *
     * @return array<int, string>
     */
    public static function facesOf(array $layout): array
    {
        return Arr::get($layout, 'kind') === self::KIND_SHEET ? self::SHEET_FACES : self::FACES;
    }

    /**
     * @return array{width?: float, length?: float, height?: float, units?: string}
     */
    private static function sizeFromSku(?ProductDevelopmentItem $item): array
    {
        $dimensions = $item?->tradeUnit?->marketing_dimensions;
        if (!is_array($dimensions)) {
            return [];
        }

        $toMm  = match (Arr::get($dimensions, 'units', 'cm')) {
            'mm' => 1,
            'm' => 1000,
            'inch' => 25.4,
            default => 10,
        };
        $sizes = collect(['width' => 'w', 'length' => 'l', 'height' => 'h'])
            ->map(fn (string $key) => round((float) Arr::get($dimensions, $key, 0) * $toMm, 1));

        if ($sizes->contains(fn (float $size) => $size <= 0)) {
            return [];
        }

        return [...$sizes->all(), 'units' => Arr::get($dimensions, 'units') === 'mm' ? 'mm' : 'cm'];
    }

    /**
     * @return array<string, mixed>
     */
    public static function rules(string $prefix = 'layout'): array
    {
        $element = $prefix.'.faces.*.elements.*';

        return [
            $prefix                          => ['required', 'array'],
            $prefix.'.kind'                  => ['nullable', Rule::in([self::KIND_BOX, self::KIND_SHEET])],
            $prefix.'.units'                 => ['required', Rule::in(['mm', 'cm'])],
            $prefix.'.width'                 => ['nullable', 'numeric', 'gt:0', 'max:'.self::MAX_SIDE_MM],
            $prefix.'.length'                => ['nullable', 'numeric', 'gt:0', 'max:'.self::MAX_SIDE_MM],
            $prefix.'.height'                => ['nullable', 'numeric', 'gt:0', 'max:'.self::MAX_SIDE_MM],
            $prefix.'.faces'                 => ['required', 'array:'.implode(',', [...self::FACES, ...self::SHEET_FACES])],
            $prefix.'.faces.*.background'                 => ['nullable', 'array'],
            $prefix.'.faces.*.background.color'           => ['nullable', 'string', 'regex:'.self::PAINT_PATTERN],
            $prefix.'.faces.*.background.image_file_id'   => ['nullable', 'integer'],
            $prefix.'.faces.*.background.x'               => ['nullable', 'numeric', 'between:0,1'],
            $prefix.'.faces.*.background.y'               => ['nullable', 'numeric', 'between:0,1'],
            $prefix.'.faces.*.background.zoom'            => ['nullable', 'numeric', 'between:1,5'],
            $prefix.'.faces.*.elements'      => ['present', 'array', 'max:'.self::MAX_ELEMENTS_PER_FACE],
            $prefix.'.faces.*.groups'        => ['nullable', 'array', 'max:'.self::MAX_ELEMENTS_PER_FACE],
            $prefix.'.faces.*.groups.*'      => ['nullable', 'string', 'max:60'],
            $element.'.id'                   => ['required', 'string', 'max:40'],
            $element.'.type'                 => ['required', Rule::in(self::ELEMENT_TYPES)],
            $element.'.x'                    => ['required', 'numeric'],
            $element.'.y'                    => ['required', 'numeric'],
            $element.'.w'                    => ['required', 'numeric', 'gt:0'],
            $element.'.h'                    => ['required', 'numeric', 'gt:0'],
            $element.'.rotate'               => ['nullable', 'numeric', 'between:-360,360'],
            $element.'.text'                 => ['nullable', 'string', 'max:2000'],
            $element.'.font_size'            => ['nullable', 'numeric', 'between:2,400'],
            $element.'.font_weight'          => ['nullable', Rule::in(['normal', 'bold'])],
            $element.'.italic'               => ['nullable', 'boolean'],
            $element.'.align'                => ['nullable', Rule::in(['left', 'center', 'right'])],
            $element.'.color'                => ['nullable', 'string', 'regex:'.self::PAINT_PATTERN],
            $element.'.shape'                => ['nullable', Rule::in(self::SHAPES)],
            $element.'.fill'                 => ['nullable', 'string', 'regex:'.self::PAINT_PATTERN],
            $element.'.stroke'               => ['nullable', 'string', 'regex:/^(#[0-9a-fA-F]{6}|none)$/'],
            $element.'.stroke_px'            => ['nullable', 'numeric', 'between:0,50'],
            $element.'.radius'               => ['nullable', 'numeric', 'between:0,500'],
            $element.'.font_family'          => ['nullable', 'string', 'max:60'],
            $element.'.image_file_id'        => ['nullable', 'integer'],
            $element.'.zone'                 => ['nullable', Rule::in(self::ZONES)],
            $element.'.label'                => ['nullable', 'string', 'max:200'],
            $element.'.source'               => ['nullable', 'string', 'max:120'],
            $element.'.group_id'             => ['nullable', 'string', 'max:40'],
            $element.'.locked'               => ['nullable', 'boolean'],
        ];
    }

    /**
     * Every face present, unknown keys dropped, so the editor can trust the shape it reads back.
     *
     * @param array<string, mixed> $layout
     *
     * @return array<string, mixed>
     */
    public static function normalise(array $layout): array
    {
        $keys = ['id', 'type', 'x', 'y', 'w', 'h', 'rotate', 'text', 'font_size', 'font_weight', 'italic', 'align', 'color', 'font_family', 'image_file_id', 'zone', 'label', 'source', 'group_id', 'locked', 'shape', 'fill', 'stroke', 'stroke_px', 'radius'];

        $kind = Arr::get($layout, 'kind') === self::KIND_SHEET ? self::KIND_SHEET : self::KIND_BOX;

        return [
            'kind'   => $kind,
            'units'  => Arr::get($layout, 'units', 'mm'),
            'width'  => self::size(Arr::get($layout, 'width')),
            'length' => $kind === self::KIND_SHEET ? null : self::size(Arr::get($layout, 'length')),
            'height' => self::size(Arr::get($layout, 'height')),
            'faces'  => collect(self::facesOf(['kind' => $kind]))->mapWithKeys(fn (string $face) => [$face => [
                'background' => [
                    'color'         => Arr::get($layout, "faces.$face.background.color", '#ffffff'),
                    'image_file_id' => Arr::get($layout, "faces.$face.background.image_file_id"),
                    'x'             => self::fraction(Arr::get($layout, "faces.$face.background.x", 0.5)),
                    'y'             => self::fraction(Arr::get($layout, "faces.$face.background.y", 0.5)),
                    'zoom'          => min(max((float) Arr::get($layout, "faces.$face.background.zoom", 1), 1), 5),
                ],
                'elements'   => collect(Arr::get($layout, "faces.$face.elements", []))
                    ->map(fn (array $element) => Arr::only($element, $keys))
                    ->values()
                    ->all(),
                'groups'     => self::groupNames(Arr::get($layout, "faces.$face", [])),
            ]])->all(),
        ];
    }

    /**
     * What a design can show straight from the SKU's trade unit: text fields, pictograms and barcodes.
     *
     * @return array{text: array<int, array{key: string, label: string}>, pictograms: array<int, array{key: string, label: string, icon: string}>, barcodes: array<int, array{key: string, label: string}>}
     */
    public static function dataSources(): array
    {
        return [
            'text'       => collect(self::textSources())->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label])->values()->all(),
            'pictograms' => collect(ProductDevelopmentTargetFieldEnum::pictograms())->map(fn (array $pictogram, string $key) => ['key' => $key, ...$pictogram])->values()->all(),
            'barcodes'   => [
                ['key' => 'unit', 'label' => __('Unit EAN-13')],
                ['key' => 'sko', 'label' => __('SKO (Code 128)')],
                ['key' => 'carton', 'label' => __('Carton (Code 128)')],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function textSources(): array
    {
        return [
            'product_name'   => __('Product name'),
            'ingredients'    => __('Ingredients'),
            'warnings'       => __('Warnings & precautions'),
            'manufacturer'   => __('Manufacturer'),
            'eu_responsible' => __('EU responsible person'),
            'ufi'            => __('UFI'),
            'un_number'      => __('UN number'),
            'net_weight'     => __('Net weight'),
            'net_volume'     => __('Net volume'),
            'origin'         => __('Country of origin'),
        ];
    }

    /**
     * The names given to a side's groups, kept only for groups that still have members.
     *
     * @param array<string, mixed> $face
     *
     * @return array<string, string>
     */
    private static function groupNames(array $face): array
    {
        $groupIds = collect(Arr::get($face, 'elements', []))->pluck('group_id')->filter()->unique();

        return collect(Arr::get($face, 'groups', []))
            ->filter(fn ($name, $groupId) => $groupIds->contains($groupId) && is_string($name) && trim($name) !== '')
            ->map(fn (string $name) => mb_substr(trim($name), 0, 60))
            ->all();
    }

    private static function fraction(mixed $value): float
    {
        return is_numeric($value) ? min(max(round((float) $value, 4), 0), 1) : 0.5;
    }

    private static function size(mixed $value): ?float
    {
        return is_numeric($value) && $value > 0 ? round((float) $value, 1) : null;
    }

    /**
     * @param array<string, mixed> $layout
     */
    /**
     * The size of the flat artwork in millimetres: the label page, or the box laid out as its net with
     * the same flaps the editor draws (resources/js/Components/Goods/ProductDevelopment/Design/boxNet.ts).
     *
     * @return array{width: float, height: float}|null
     */
    public static function netSize(array $layout): ?array
    {
        if (!self::hasSize($layout)) {
            return null;
        }

        $width  = (float) $layout['width'];
        $height = (float) $layout['height'];
        if (Arr::get($layout, 'kind') === self::KIND_SHEET) {
            return ['width' => $width, 'height' => $height];
        }

        $length = (float) $layout['length'];
        $tuck   = min(max($length * 0.6, 8), 25);
        $glue   = min(max($length * 0.35, 8), 15);

        return [
            'width'  => 2 * $length + 2 * $width + $glue,
            'height' => $tuck * 2 + $length * 2 + $height,
        ];
    }

    public static function hasSize(array $layout): bool
    {
        if (Arr::get($layout, 'kind') === self::KIND_SHEET) {
            return self::size(Arr::get($layout, 'width')) && self::size(Arr::get($layout, 'height'));
        }

        return self::size(Arr::get($layout, 'width')) && self::size(Arr::get($layout, 'length')) && self::size(Arr::get($layout, 'height'));
    }

    /**
     * @param array<string, mixed> $layout
     *
     * @return array<int, int>
     */
    public static function imageFileIds(array $layout): array
    {
        return collect(Arr::get($layout, 'faces', []))
            ->flatMap(fn (array $face) => [
                Arr::get($face, 'background.image_file_id'),
                ...collect(Arr::get($face, 'elements', []))->pluck('image_file_id')->all(),
            ])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
