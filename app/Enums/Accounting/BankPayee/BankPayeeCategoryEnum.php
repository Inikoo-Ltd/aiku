<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 15 Nov 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Accounting\BankPayee;

use App\Enums\EnumHelperTrait;

enum BankPayeeCategoryEnum: string
{
    use EnumHelperTrait;

    case SUPPLIERS = 'suppliers';
    case OWN_COMPANIES = 'own_companies';
    case STAFF = 'staff';
    case TAX = 'tax';
    case SERVICES = 'services';
    case CUSTOMER_REFUNDS = 'customer_refunds';
    case BANK_FEES = 'bank_fees';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::SUPPLIERS     => __('Suppliers and agents'),
            self::OWN_COMPANIES => __('Our companies'),
            self::STAFF         => __('Staff and contractors'),
            self::TAX           => __('Tax and duty'),
            self::SERVICES         => __('Services and subscriptions'),
            self::CUSTOMER_REFUNDS => __('Customer refunds'),
            self::BANK_FEES     => __('Bank fees'),
            self::OTHER         => __('Other'),
        };
    }

    /**
     * What a payee of this category can be linked to.
     *
     * @return array<int, string>
     */
    public function linkTypes(): array
    {
        return match ($this) {
            self::SUPPLIERS     => ['Supplier', 'Agent', 'Organisation'],
            self::OWN_COMPANIES => ['Organisation'],
            self::STAFF         => ['Employee'],
            default             => [],
        };
    }
}
