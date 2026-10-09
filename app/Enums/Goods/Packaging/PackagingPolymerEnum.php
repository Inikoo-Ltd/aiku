<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 10 Oct 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Goods\Packaging;

use App\Enums\EnumHelperTrait;

enum PackagingPolymerEnum: string
{
    use EnumHelperTrait;

    case PET   = 'pet';
    case HDPE  = 'hdpe';
    case PVC   = 'pvc';
    case LDPE  = 'ldpe';
    case PP    = 'pp';
    case PS    = 'ps';
    case EPS   = 'eps';
    case BIO   = 'bio';
    case OTHER = 'other';

    public static function labels(): array
    {
        return [
            'pet'   => 'PET',
            'hdpe'  => 'HDPE',
            'pvc'   => 'PVC',
            'ldpe'  => 'LDPE',
            'pp'    => 'PP',
            'ps'    => 'PS',
            'eps'   => 'EPS',
            'bio'   => __('Bio-based'),
            'other' => __('Other plastic'),
        ];
    }

    public static function fromMaterialCode(?string $materialCode): ?self
    {
        $code = strtoupper(trim((string)$materialCode));

        return match (true) {
            str_starts_with($code, 'PET')   => self::PET,
            str_starts_with($code, 'PE-HD') => self::HDPE,
            str_starts_with($code, 'PE-LD') => self::LDPE,
            str_starts_with($code, 'PVC')   => self::PVC,
            str_starts_with($code, 'PP')    => self::PP,
            str_starts_with($code, 'EPS')   => self::EPS,
            str_starts_with($code, 'PS')    => self::PS,
            str_starts_with($code, 'O 7')   => self::OTHER,
            default                         => null,
        };
    }
}
