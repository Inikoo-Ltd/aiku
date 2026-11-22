<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\CRM\CustomerClaim;

use App\Enums\EnumHelperTrait;

/**
 * What a claim is about. Items are product problems (damaged, missing, wrong, faulty, lost) and are
 * the only kind counted in the product figures and the auto-approve allowance. A refund is money
 * refunded from an invoice; a tax correction is a tax-only refund, which accountants make.
 */
enum CustomerClaimKindEnum: string
{
    use EnumHelperTrait;

    case ITEMS = 'items';
    case REFUND = 'refund';
    case TAX_CORRECTION = 'tax_correction';

    public function label(): string
    {
        return match ($this) {
            self::ITEMS          => __('Items'),
            self::REFUND         => __('Refund'),
            self::TAX_CORRECTION => __('Tax correction'),
        };
    }
}
