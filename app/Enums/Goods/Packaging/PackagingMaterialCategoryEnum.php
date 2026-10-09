<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 Oct 2026 19:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Goods\Packaging;

use App\Enums\EnumHelperTrait;

/**
 * The material streams EPR returns are reported in.
 */
enum PackagingMaterialCategoryEnum: string
{
    use EnumHelperTrait;

    case PAPER_CARDBOARD = 'paper_cardboard';
    case PLASTIC         = 'plastic';
    case GLASS           = 'glass';
    case STEEL           = 'steel';
    case ALUMINIUM       = 'aluminium';
    case WOOD            = 'wood';
    case TEXTILE         = 'textile';
    case COMPOSITE       = 'composite';
    case OTHER           = 'other';

    public static function labels(): array
    {
        return [
            'paper_cardboard' => __('Paper / cardboard'),
            'plastic'         => __('Plastic'),
            'glass'           => __('Glass'),
            'steel'           => __('Steel'),
            'aluminium'       => __('Aluminium'),
            'wood'            => __('Wood'),
            'textile'         => __('Textile'),
            'composite'       => __('Composite'),
            'other'           => __('Other'),
        ];
    }

    public static function fromSheet(?string $material, ?string $materialCode): self
    {
        $code = strtoupper(trim((string)$materialCode));
        $fromCode = match (true) {
            str_starts_with($code, 'C/')                       => self::COMPOSITE,
            str_starts_with($code, 'PAP')                      => self::PAPER_CARDBOARD,
            str_starts_with($code, 'GL')                       => self::GLASS,
            str_starts_with($code, 'FE')                       => self::STEEL,
            str_starts_with($code, 'ALU')                      => self::ALUMINIUM,
            str_starts_with($code, 'FOR')                      => self::WOOD,
            str_starts_with($code, 'TEX')                      => self::TEXTILE,
            (bool)preg_match('/^(PET|PE-|PP|PS|PVC|O 7)/', $code) => self::PLASTIC,
            default                                            => null,
        };
        if ($fromCode) {
            return $fromCode;
        }

        $material = mb_strtolower((string)$material);

        return match (true) {
            str_contains($material, 'composite')                                                     => self::COMPOSITE,
            (bool)preg_match('/board|paper|pulp|kraft|tissue/', $material)                           => self::PAPER_CARDBOARD,
            (bool)preg_match('/\b(pe-ld|pe-hd|pp|pet|ps|pvc|eva)\b|foam|plastic|adhesive tape/', $material) => self::PLASTIC,
            str_contains($material, 'glass')                                                         => self::GLASS,
            str_contains($material, 'steel')                                                         => self::STEEL,
            str_contains($material, 'alumin')                                                        => self::ALUMINIUM,
            str_contains($material, 'wood'), str_contains($material, 'cork')                         => self::WOOD,
            (bool)preg_match('/textile|cotton|jute|hessian/', $material)                             => self::TEXTILE,
            default                                                                                  => self::OTHER,
        };
    }
}
