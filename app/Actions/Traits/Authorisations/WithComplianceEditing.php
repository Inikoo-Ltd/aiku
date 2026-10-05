<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 30 Sep 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Authorisations;

use App\Models\Catalogue\Product;
use App\Models\Goods\TradeUnit;
use App\Models\Goods\TradeUnitFamily;
use App\Models\SysAdmin\User;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

trait WithComplianceEditing
{
    public const array COMPLIANCE_FIELDS = [
        'cpnp_number',
        'scpn_number',
        'ufi_number',
        'gross_weight',
        'net_weight',
        'marketing_weight',
        'marketing_dimensions',
        'barcode',
        'barcode_id',
        'barcode_choice',
        'independent_barcode',
        'un_number',
        'un_class',
        'packing_group',
        'proper_shipping_name',
        'hazard_identification_number',
        'ce_marking',
        'ukca_marking',
        'weee_symbol',
        'ip_rating',
        'sorting_recycling_information',
        'safety_icons',
        'batch_number',
        'show_net_quantity',
        'markets',
        'languages',
        'best_before',
        'packaging_material_codes',
        'packaging_material_codes_show',
        'label_info',
        'label_info_approved',
        'tariff_code',
        'duty_rate',
        'hts_us',
        'marketing_ingredients',
        'ingredients',
        'country_of_origin',
        'origin_country_id',
    ];

    public static function isComplianceField(string $field): bool
    {
        return in_array($field, self::COMPLIANCE_FIELDS, true)
            || Str::startsWith($field, ['gpsr', 'pictogram_', '_']);
    }

    protected function canChangeComplianceDocuments(?User $user, TradeUnit|TradeUnitFamily|Product $model): bool
    {
        if (!$user) {
            return false;
        }

        $permissions = $model instanceof Product
            ? ["products.$model->shop_id.edit", "web.$model->shop_id.edit", 'group-webmaster.edit', 'masters.edit']
            : ['goods.edit'];

        return $user->authTo([...$permissions, 'compliance.edit']);
    }

    protected function canPublishCompliance(): bool
    {
        return (bool) request()->user()?->authTo('compliance.publish');
    }

    protected function isComplianceOnlyEditor(): bool
    {
        return !$this->asAction && !$this->canEdit && $this->canEditCompliance;
    }

    protected function rejectNonComplianceFields(Validator $validator): void
    {
        if (!$this->isComplianceOnlyEditor()) {
            return;
        }

        $routeParameters = request()->route()?->parameterNames() ?? [];

        foreach (array_keys($validator->getData()) as $field) {
            if (!in_array($field, $routeParameters, true) && !self::isComplianceField($field)) {
                $validator->errors()->add($field, __('You can only change compliance information'));
            }
        }

        if (array_key_exists('label_info_approved', $validator->getData()) && !$this->canPublishCompliance()) {
            $validator->errors()->add('label_info_approved', __('Only compliance managers and supervisors can publish label information'));
        }
    }

    protected function complianceOnlyBlueprint(array $blueprint): array
    {
        if (!$this->isComplianceOnlyEditor()) {
            return $blueprint;
        }

        return collect($blueprint)
            ->map(function (array $section) {
                $section['fields'] = collect($section['fields'] ?? [])
                    ->filter(fn ($field, $key) => is_string($key) && self::isComplianceField($key))
                    ->reject(fn ($field, $key) => $key === 'label_info_approved' && !$this->canPublishCompliance())
                    ->all();

                return $section;
            })
            ->filter(fn (array $section) => !empty($section['fields']))
            ->values()
            ->all();
    }
}
