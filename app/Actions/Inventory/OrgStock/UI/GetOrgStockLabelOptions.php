<?php

/*
 * Author Louis Perez
 * Created on 23-09-2026-11h-34m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Inventory\OrgStock\UI;

use App\Models\Inventory\OrgStock;
use App\Models\SupplyChain\SupplierProduct;
use App\Models\SysAdmin\Organisation;
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

    private const PREVIEWED_FIELDS = [
        'with_account_signature'     => 'signature',
        'with_eu_responsible_person' => 'eu_responsible_person',
        'with_es_importer'           => 'es_importer',
    ];

    /**
     * @return array<string, mixed>
     */
    public function handle(OrgStock $orgStock, ?int $supplierProductId = null, ?Organisation $commercialisingOrganisation = null): array
    {
        $supplierProduct = GetOrgStockLabelData::make()->getSupplierProduct($orgStock, $supplierProductId);

        $labels = [
            'sko'    => GetOrgStockLabelData::run($orgStock, 'sko', $supplierProduct),
            'unit'   => GetOrgStockLabelData::run($orgStock, 'unit', $supplierProduct),
            'carton' => GetOrgStockLabelData::run($orgStock, 'carton', $supplierProduct, $commercialisingOrganisation),
        ];

        return [
            'sizes'         => $this->getSizes(),
            'default_size'  => PdfOrgStockLabel::DEFAULT_SIZE,
            'layouts'       => $this->getLayouts(),
            'fields'        => $this->getFields($labels),
            'levels'        => $this->getLevels($labels),
            'custom_text_max_length' => 255,
            'unified'       => $this->getUnified($orgStock, $supplierProduct),
            'carton_organisations' => GetOrgStockLabelData::make()->getCartonOrganisations($orgStock)
                ->map(fn (Organisation $organisation) => ['id' => $organisation->id, 'code' => $organisation->code, 'name' => $organisation->name])
                ->values()
                ->all(),
            'carton_organisation_id' => $commercialisingOrganisation->id ?? $orgStock->organisation_id,
        ];
    }

    /**
     * The unified label has no tick boxes: per level it either prints or says what is missing. The
     * batch is the one thing typed, so the reasons are worked out with a batch in place and the
     * modal checks what is typed against the same pattern.
     *
     * @return array<string, mixed>
     */
    private function getUnified(OrgStock $orgStock, ?SupplierProduct $supplierProduct): array
    {
        $batch  = GetOrgStockUnifiedLabelData::run($orgStock, 'unit', $supplierProduct)['batch'];
        $levels = [];

        foreach (GetOrgStockUnifiedLabelData::SIZES as $level => $size) {
            if ($level === 'sko' && (int) ($orgStock->packed_in ?? 1) <= 1) {
                continue;
            }

            $levels[$level] = [
                'size'     => trimDecimalZeros($size['width']).' x '.trimDecimalZeros($size['height']),
                'blockers' => GetOrgStockUnifiedLabelData::run($orgStock, $level, $supplierProduct, $batch ?? 'BATCH')['blockers'],
            ];
        }

        $tradeUnit         = $orgStock->tradeUnits->count() === 1 ? $orgStock->tradeUnits->first() : null;
        $missingNameBlockers = $tradeUnit ? GetOrgStockUnifiedLabelData::make()->getMissingNameBlockers($tradeUnit) : [];

        return [
            'batch'           => $batch,
            'batch_pattern'   => trim(GetOrgStockUnifiedLabelData::BATCH_PATTERN, '/'),
            'levels'          => $levels,
            'translate_names' => $missingNameBlockers ? [
                'route'    => [
                    'name'       => 'grp.models.trade-unit.translate_names',
                    'parameters' => ['tradeUnit' => $tradeUnit->id],
                ],
                'blockers' => $missingNameBlockers,
            ] : null,
        ];
    }

    /**
     * @return array<string, array<int, array<string, string>>>
     */
    public function getSizes(): array
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
     * Every size prints one label at a time or on the sheet that size is die cut on, which is A4
     * except for the sizes cut by hand from plain A5.
     *
     * @return array<string, array<int, array<string, string>>>
     */
    private function getLayouts(): array
    {
        $layouts = [];

        foreach (PdfOrgStockLabel::SHEETS as $size => $sheet) {
            $layouts[$size] = [
                ['key' => 'single', 'label' => __('Single')],
                [
                    'key'   => 'sheet',
                    'label' => __(':paper :count labels (:code)', [
                        'paper' => $sheet['paper'] ?? 'A4',
                        'count' => $sheet['columns'] * $sheet['rows'],
                        'code'  => $sheet['code'],
                    ]),
                ],
            ];
        }

        return $layouts;
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

        foreach (['sko' => __('SKO'), 'unit' => __('Unit'), 'carton' => __('Carton')] as $level => $label) {
            $levels[] = [
                'key'       => $level,
                'label'     => $label,
                'printable' => match ($level) {
                    'sko'    => true,
                    'unit'   => filled($labels[$level]['barcode']['number']),
                    'carton' => filled($labels[$level]['barcode']['number']) || filled($labels[$level]['carton']['units_per_carton']),
                },
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
            'with_eu_responsible_person' => [__('With EU responsible person'), 'eu_responsible_person', null, true],
            'with_es_importer'       => [__('With ES importer'), 'es_importer', null, false],
            'with_ingredients'       => [__('With ingredients/materials'), 'materials', __('This item has no materials'), true],
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
                    $default,
                    isset(self::PREVIEWED_FIELDS[$key]) ? $label[self::PREVIEWED_FIELDS[$key]] : null
                );
            }
        }

        return $fields;
    }

    /**
     * The signature, the EU responsible person and the ES importer are names and addresses nobody can
     * guess from the tick box, so their text comes along for the modal to show what will be printed.
     *
     * @return array<string, mixed>
     */
    private function getField(string $key, string $label, mixed $value, ?string $reason = null, bool $default = true, ?string $preview = null): array
    {
        $available = filled($value);

        return [
            'key'       => $key,
            'label'     => $label,
            'available' => $available,
            'checked'   => $available && $default,
            'reason'    => $available ? null : $reason,
            'preview'   => $available ? $preview : null,
        ];
    }
}
