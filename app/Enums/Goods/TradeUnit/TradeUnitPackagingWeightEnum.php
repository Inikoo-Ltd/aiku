<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 16 Nov 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Goods\TradeUnit;

use App\Enums\EnumHelperTrait;

/**
 * Materials of a unit's packaging, declared in grams per unit, grouped in the material categories they are reported under.
 */
enum TradeUnitPackagingWeightEnum: string
{
    use EnumHelperTrait;

    case GLASS = 'glass';
    case PET = 'pet';
    case HDPE = 'hdpe';
    case PVC = 'pvc';
    case LDPE = 'ldpe';
    case PP = 'pp';
    case PS = 'ps';
    case XPS = 'xps';
    case EPS = 'eps';
    case OTHER_PLASTICS = 'other_plastics';
    case PAPER = 'paper';
    case CARDBOARD = 'cardboard';
    case MOULDED_PULP = 'moulded_pulp';
    case FERROUS_METALS = 'ferrous_metals';
    case ALUMINIUM = 'aluminium';
    case WOOD = 'wood';
    case OTHER = 'other';

    public function category(): string
    {
        return match ($this) {
            self::GLASS => __('Glass'),
            self::PET => 'PET',
            self::HDPE, self::PVC, self::LDPE, self::PP, self::PS, self::XPS, self::EPS, self::OTHER_PLASTICS => __('Plastics excluding PET'),
            self::PAPER, self::CARDBOARD, self::MOULDED_PULP => __('Paper and cardboard'),
            self::FERROUS_METALS, self::ALUMINIUM => __('Metals'),
            self::WOOD => __('Wood'),
            self::OTHER => __('Other'),
        };
    }

    public function material(): string
    {
        return match ($this) {
            self::GLASS => __('Glass'),
            self::PET => 'PET',
            self::HDPE => 'HDPE',
            self::PVC => 'PVC',
            self::LDPE => 'LDPE',
            self::PP => 'PP',
            self::PS => 'PS',
            self::XPS => 'XPS',
            self::EPS => 'EPS',
            self::OTHER_PLASTICS => __('Other plastics'),
            self::PAPER => __('Paper'),
            self::CARDBOARD => __('Cardboard'),
            self::MOULDED_PULP => __('Moulded pulp'),
            self::FERROUS_METALS => __('Ferrous metals'),
            self::ALUMINIUM => __('Aluminium'),
            self::WOOD => __('Wood'),
            self::OTHER => __('Other'),
        };
    }

    public static function labels(): array
    {
        $labels = [];
        foreach (self::cases() as $case) {
            $labels[$case->value] = $case->category() === $case->material() ? $case->material() : $case->category().' – '.$case->material();
        }

        return $labels;
    }

    public function field(): string
    {
        return 'packaging_weight_'.$this->value;
    }
}
