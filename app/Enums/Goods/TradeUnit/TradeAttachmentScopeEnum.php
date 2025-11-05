<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 Oct 2025 16:36:14 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Enums\Goods\TradeUnit;

use App\Enums\EnumHelperTrait;

enum TradeAttachmentScopeEnum: string
{
    use EnumHelperTrait;

    case IFRA = 'ifra';
    case SDS = 'sds';
    case ALLERGEN_DECLARATIONS = 'allergen_declarations';
    case DOC = 'doc';
    case CPSR = 'cpsr';

    case IFRA_PRIVATE = 'ifra_private';
    case SDS_PRIVATE = 'sds_private';
    case ALLERGEN_DECLARATIONS_PRIVATE = 'allergen_declarations_private';
    case DOC_PRIVATE = 'doc_private';
    case CPSR_PRIVATE = 'cpsr_private';


    public static function labels(): array
    {
        return [
            'ifra'                          => 'IFRA',
            'sds'                           => 'SDS',
            'allergen_declarations'         => __('Allergen Declarations'),
            'doc'                           => __('Declaration of Conformity'),
            'cpsr'                          => 'CPSR',
            'ifra_private'                  => 'UFRA'.' ('.__('Private').')',
            'sds_private'                   => 'SDS'.' ('.__('Private').')',
            'allergen_declarations_private' => __('Allergen Declarations').' ('.__('Private').')',
            'doc_private'                   => __('Declaration of Conformity (Private)').' ('.__('Private').')',
            'cpsr_private'                  => 'CPSR'.' ('.__('Private').')',
        ];
    }


}
