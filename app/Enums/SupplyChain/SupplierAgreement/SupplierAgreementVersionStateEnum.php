<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\SupplyChain\SupplierAgreement;

use App\Enums\EnumHelperTrait;

enum SupplierAgreementVersionStateEnum: string
{
    use EnumHelperTrait;

    case DRAFT = 'draft';
    case PUBLISHED = 'published';
    case RETIRED = 'retired';

    public static function labels(): array
    {
        return [
            'draft'     => __('Draft'),
            'published' => __('Published'),
            'retired'   => __('Retired'),
        ];
    }

    public function label(): string
    {
        return self::labels()[$this->value];
    }
}
