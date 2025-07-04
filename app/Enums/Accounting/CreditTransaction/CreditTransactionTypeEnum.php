<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 Aug 2024 10:23:46 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Accounting\CreditTransaction;

use App\Enums\EnumHelperTrait;

enum CreditTransactionTypeEnum: string
{
    use EnumHelperTrait;

    case TOP_UP             = 'top_up';
    case PAYMENT            = 'payment';
    case ADJUST             = 'adjust';
    case CANCEL             = 'cancel';
    case RETURN             = 'return';
    case PAY_RETURN         = 'pay_return';
    case ADD_FUNDS_OTHER    = 'add_funds_other';
    case COMPENSATION       = 'compensation';
    case TRANSFER_IN        = 'transfer_in';
    case FROM_EXCESS        = 'from_excess';  // when a payment exceed the total amount of the invoice/order, remaining amount is added as credit
    case MONEY_BACK         = 'money_back';
    case TRANSFER_OUT       = 'transfer_out';
    case REMOVE_FUNDS_OTHER = 'remove_funds_other';

    public function label(): string
    {
        return match ($this) {
            CreditTransactionTypeEnum::TOP_UP => 'Top up',
            CreditTransactionTypeEnum::PAYMENT => 'Payment',
            CreditTransactionTypeEnum::ADJUST => 'Adjust',
            CreditTransactionTypeEnum::CANCEL => 'Cancel',
            CreditTransactionTypeEnum::RETURN => 'Return',
            CreditTransactionTypeEnum::PAY_RETURN => 'Pay return',
            CreditTransactionTypeEnum::ADD_FUNDS_OTHER => 'Add funds (other)',
            CreditTransactionTypeEnum::COMPENSATION => 'Compensation',
            CreditTransactionTypeEnum::TRANSFER_IN => 'Transfer in',
            CreditTransactionTypeEnum::FROM_EXCESS => 'From excess',
            CreditTransactionTypeEnum::MONEY_BACK => 'Money back',
            CreditTransactionTypeEnum::TRANSFER_OUT => 'Transfer out',
            CreditTransactionTypeEnum::REMOVE_FUNDS_OTHER => 'Remove funds (other)',
        };
    }

    public static function getOptions(): array
    {
        return array_map(
            fn($case) => [
                'value' => $case->value,
                'label' => $case->label(),
            ],
            self::cases()
        );
    }


}
