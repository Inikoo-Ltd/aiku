<?php
/*
 * author Arya Permana - Kirin
 * created on 04-07-2025-18h-07m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Enums\Accounting\CreditTransaction;

use App\Enums\EnumHelperTrait;

enum CreditTransactionReasonEnum: string
{
    use EnumHelperTrait;

    case PAY_FOR_SHIPPING      = 'pay_for_shipping';
    case COMPENSATE_CUSTOMER   = 'compensate_customer';
    case TRANSFER              = 'transfer';
    case OTHER                 = 'other';

    public function label(): string
    {
        return match ($this) {
            CreditTransactionReasonEnum::PAY_FOR_SHIPPING => 'Pay for the shipping of a return',
            CreditTransactionReasonEnum::COMPENSATE_CUSTOMER => 'Compensate customer',
            CreditTransactionReasonEnum::TRANSFER => 'Transfer from other customer account',
            CreditTransactionReasonEnum::OTHER => 'Othe reason',
        };
    }


}
