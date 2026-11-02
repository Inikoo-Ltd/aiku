<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-10-20 09:10
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Enums\SupplyChain\PackingSpec;

use App\Enums\EnumHelperTrait;

enum PackingSpecRoleEnum: string
{
    use EnumHelperTrait;

    case WRAP          = 'wrap';
    case BAG           = 'bag';
    case VOID_FILL     = 'void_fill';
    case CUSHIONING    = 'cushioning';
    case DESICCANT     = 'desiccant';
    case DIVIDER       = 'divider';
    case PACK_BOX      = 'pack_box';
    case MASTER_CARTON = 'master_carton';
    case STRAPPING     = 'strapping';
    case TAPE          = 'tape';
    case PALLET_WRAP   = 'pallet_wrap';
    case LABEL_INSERT  = 'label_insert';

    public static function labels(): array
    {
        return [
            'wrap'          => __('Wrap'),
            'bag'           => __('Bag'),
            'void_fill'     => __('Void fill'),
            'cushioning'    => __('Cushioning'),
            'desiccant'     => __('Desiccant'),
            'divider'       => __('Carton divider'),
            'pack_box'      => __('Pack box'),
            'master_carton' => __('Master carton'),
            'strapping'     => __('Strapping'),
            'tape'          => __('Tape'),
            'pallet_wrap'   => __('Pallet wrap'),
            'label_insert'  => __('Label / insert'),
        ];
    }
}
