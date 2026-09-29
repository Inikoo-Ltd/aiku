<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\SupplyChain;

use App\Enums\EnumHelperTrait;

enum SupplyChainAttachmentScopeEnum: string
{
    use EnumHelperTrait;

    case CONTRACT          = 'Contract';
    case CERTIFICATE       = 'Certificate';
    case SAFETY_DATA_SHEET = 'Safety data sheet';
    case TEST_REPORT       = 'Test report';
    case PRICE_LIST        = 'Price list';
    case CATALOGUE         = 'Catalogue';
    case COMPANY_DOCUMENTS = 'Company documents';
    case OTHER             = 'Other';

    public static function labels(): array
    {
        return [
            'Contract'          => __('Contract'),
            'Certificate'       => __('Certificate'),
            'Safety data sheet' => __('Safety data sheet (SDS)'),
            'Test report'       => __('Test report'),
            'Price list'        => __('Price list'),
            'Catalogue'         => __('Catalogue'),
            'Company documents' => __('Company documents'),
            'Other'             => __('Other'),
        ];
    }

    /**
     * @return array<int, array{name: string, code: string}>
     */
    public static function options(): array
    {
        return collect(self::labels())
            ->map(fn (string $label, string $code) => ['name' => $label, 'code' => $code])
            ->values()
            ->all();
    }
}
