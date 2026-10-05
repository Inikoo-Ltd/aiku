<?php

/*
 * Author Louis Perez
 * Created on 23-09-2026-11h-34m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Inventory\OrgStock\UI;

use App\Models\Inventory\OrgStock;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * What the label modal needs to draw itself: the stocks that can be printed on, and, per barcode
 * level, which fields this org stock actually holds a value for.
 *
 * A field with no value comes back unavailable so its tick box can be greyed out with the reason
 * showing, rather than being left on for someone to print a label with a hole in it.
 */
class GetOrgStockLabelOptions
{
    use AsObject;

    /**
     * @return array<string, mixed>
     */
    public function handle(OrgStock $orgStock): array
    {
        $labels = [
            'sko'  => GetOrgStockLabelData::run($orgStock, 'sko'),
            'unit' => GetOrgStockLabelData::run($orgStock, 'unit'),
        ];

        return [
            'sizes'         => $this->getSizes(),
            'default_size'  => PdfOrgStockLabel::DEFAULT_SIZE,
            'layouts'       => [
                ['key' => 'single', 'label' => __('Single')],
                ['key' => 'sheet', 'label' => __('A4 27 labels (EU30161)')],
            ],
            'fields'        => $this->getFields($labels),
            'levels'        => $this->getLevels($labels),
            'custom_text_max_length' => 255,
        ];
    }

    /**
     * @return array<string, array<int, array<string, string>>>
     */
    private function getSizes(): array
    {
        $sizes = [];

        foreach (PdfOrgStockLabel::LEVEL_SIZES as $level => $keys) {
            foreach ($keys as $key) {
                $size          = PdfOrgStockLabel::SIZES[$key];
                $sizes[$level][] = [
                    'key'   => $key,
                    'label' => trimDecimalZeros($size['width']).' x '.trimDecimalZeros($size['height']),
                ];
            }
        }

        return $sizes;
    }

    /**
     * The unit label is built around its barcode and cannot print without one. The SKO label is a
     * box label that carries no barcode at all, so it prints whatever the org stock has been given.
     *
     * @param  array<string, array<string, mixed>>  $labels
     * @return array<int, array<string, mixed>>
     */
    private function getLevels(array $labels): array
    {
        $levels = [];

        foreach (['sko' => __('SKO'), 'unit' => __('Unit')] as $level => $label) {
            $levels[] = [
                'key'       => $level,
                'label'     => $label,
                'printable' => $level === 'sko' || filled($labels[$level]['barcode']['number']),
            ];
        }

        return $levels;
    }

    /**
     * @param  array<string, array<string, mixed>>  $labels
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function getFields(array $labels): array
    {
        $fields = [];

        $all = [
            'with_image'             => [__('With image'), 'has_image', __('This item has no image'), true],
            'with_made_in'           => [__('With made in'), 'made_in', __('This item has no country of origin'), true],
            'with_manufactured_by'   => [__('With manufactured by'), 'manufactured_by', __('This item has no manufacturer'), true],
            'with_weight'            => [__('With weight'), 'weight', __('This item has no weight'), true],
            'with_custom_text'       => [__('With custom text'), null, null, false],
            'with_account_signature' => [__('With account signature'), 'signature', __('This organisation has no address'), false],
        ];

        foreach ($labels as $level => $label) {
            $fields[$level] = [];

            foreach (PdfOrgStockLabel::LEVEL_FIELDS[$level] as $key) {
                [$fieldLabel, $source, $reason, $default] = $all[$key];

                $fields[$level][] = $this->getField(
                    $key,
                    $fieldLabel,
                    $source === null ? true : $label[$source],
                    $reason,
                    $default
                );
            }
        }

        return $fields;
    }

    /**
     * @return array<string, mixed>
     */
    private function getField(string $key, string $label, mixed $value, ?string $reason = null, bool $default = true): array
    {
        $available = filled($value);

        return [
            'key'       => $key,
            'label'     => $label,
            'available' => $available,
            'checked'   => $available && $default,
            'reason'    => $available ? null : $reason,
        ];
    }
}
