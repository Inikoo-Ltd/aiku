<?php

/*
 * Author Louis Perez
 * Created on 11-09-2026-11h-52m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Enums\Goods\TradeUnit;

use App\Enums\EnumHelperTrait;

enum TradeUnitPackagingMaterialEnum: string
{
    use EnumHelperTrait;

    case PET_1 = 'pet_1';
    case HDPE_2 = 'hdpe_2';
    case PVC_3 = 'pvc_3';
    case LDPE_4 = 'ldpe_4';
    case PP_5 = 'pp_5';
    case PS_6 = 'ps_6';
    case PAP_20 = 'pap_20';
    case PAP_21 = 'pap_21';
    case PAP_22 = 'pap_22';
    case FE_40 = 'fe_40';
    case ALU_41 = 'alu_41';
    case FOR_50 = 'for_50';
    case FOR_51 = 'for_51';
    case TEX_60 = 'tex_60';
    case TEX_61 = 'tex_61';
    case GL_70 = 'gl_70';
    case GL_71 = 'gl_71';
    case GL_72 = 'gl_72';

    public static function labels(): array
    {
        return [
            'pet_1'  => 'PET 1',
            'hdpe_2' => 'HDPE 2',
            'pvc_3'  => 'PVC 3',
            'ldpe_4' => 'LDPE 4',
            'pp_5'   => 'PP 5',
            'ps_6'   => 'PS 6',
            'pap_20' => 'PAP 20',
            'pap_21' => 'PAP 21',
            'pap_22' => 'PAP 22',
            'fe_40'  => 'FE 40',
            'alu_41' => 'ALU 41',
            'for_50' => 'FOR 50',
            'for_51' => 'FOR 51',
            'tex_60' => 'TEX 60',
            'tex_61' => 'TEX 61',
            'gl_70'  => 'GL 70',
            'gl_71'  => 'GL 71',
            'gl_72'  => 'GL 72',
        ];
    }

    public static function materials(): array
    {
        return [
            'pet_1'  => __('PET'),
            'hdpe_2' => __('HDPE'),
            'pvc_3'  => __('PVC'),
            'ldpe_4' => __('LDPE'),
            'pp_5'   => __('PP'),
            'ps_6'   => __('PS'),
            'pap_20' => __('Corrugated cardboard'),
            'pap_21' => __('Non-corrugated cardboard'),
            'pap_22' => __('Paper'),
            'fe_40'  => __('Steel'),
            'alu_41' => __('Aluminium'),
            'for_50' => __('Wood'),
            'for_51' => __('Cork'),
            'tex_60' => __('Cotton'),
            'tex_61' => __('Jute'),
            'gl_70'  => __('Clear glass'),
            'gl_71'  => __('Green glass'),
            'gl_72'  => __('Brown glass'),
        ];
    }

    public function abbreviation(): string
    {
        return strtoupper(explode('_', $this->value)[0]);
    }

    public function code(): int
    {
        return (int) explode('_', $this->value)[1];
    }

    public function material(): string
    {
        return self::materials()[$this->value];
    }
}
