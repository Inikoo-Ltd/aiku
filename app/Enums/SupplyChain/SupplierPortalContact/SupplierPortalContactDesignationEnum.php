<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\SupplyChain\SupplierPortalContact;

use App\Enums\EnumHelperTrait;

/**
 * What a person at the supplier does, so purchasing writes to the one doing the work, not only to the boss.
 */
enum SupplierPortalContactDesignationEnum: string
{
    use EnumHelperTrait;

    case SALES_MANAGER = 'sales_manager';
    case ACCOUNTS = 'accounts';
    case ASSISTANT = 'assistant';
    case DESIGNER = 'designer';
    case OTHER = 'other';

    public static function labels(): array
    {
        return [
            'sales_manager' => __('Sales manager'),
            'accounts'      => __('Accounts department'),
            'assistant'     => __('Assistant'),
            'designer'      => __('Designer'),
            'other'         => __('Other'),
        ];
    }

    public function label(): string
    {
        return self::labels()[$this->value];
    }
}
