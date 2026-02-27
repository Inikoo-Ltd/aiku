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
    case MSDS = 'msds'; // deprecated
    case CLP = 'clp';
    case ALLERGEN_DECLARATIONS = 'allergen_declarations';
    case DOC = 'doc';
    case CPSR = 'cpsr';
    case TEST_REPORTS = 'test_reports';
    case OTHER = 'other';

    case IFRA_PRIVATE = 'ifra_private';
    case SDS_PRIVATE = 'sds_private';
    case MSDS_PRIVATE = 'msds_private';
    case CLP_PRIVATE = 'clp_private';
    case ALLERGEN_DECLARATIONS_PRIVATE = 'allergen_declarations_private';
    case DOC_PRIVATE = 'doc_private';
    case CPSR_PRIVATE = 'cpsr_private';
    case TEST_REPORTS_PRIVATE = 'test_reports_private';
    case OTHER_PRIVATE = 'other_private';


    public static function labels(): array
    {
        return [
            'ifra'                          => 'IFRA',
            'sds'                           => 'SDS',
            'clp'                           => 'CLP',
            'msds'                          => 'MSDS',
            'allergen_declarations'         => __('Allergen Declarations'),
            'doc'                           => __('Declaration of Conformity'),
            'cpsr'                          => 'CPSR',
            'test_reports'                  => __('Test Reports'),
            'other'                         => __('Other'),
            'ifra_private'                  => 'UFRA'.' ('.__('Private').')',
            'sds_private'                   => 'SDS'.' ('.__('Private').')',
            'clp_private'                   => 'CLP'.' ('.__('Private').')',
            'msds_private'                  => 'MSDS'.' ('.__('Private').')',
            'allergen_declarations_private' => __('Allergen Declarations').' ('.__('Private').')',
            'doc_private'                   => __('Declaration of Conformity (Private)').' ('.__('Private').')',
            'cpsr_private'                  => 'CPSR'.' ('.__('Private').')',
            'test_reports_private'          => __('Test Reports').' ('.__('Private').')',
            'other_private'                 => __('Other').' ('.__('Private').')',
        ];
    }


}
