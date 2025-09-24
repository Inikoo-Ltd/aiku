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

    case PAY_FOR_SHIPPING = 'pay_for_shipping';
    case COMPENSATE_CUSTOMER = 'compensate_customer';
    case TRANSFER = 'transfer';
    case MONEY_BACK = 'money_back';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            CreditTransactionReasonEnum::PAY_FOR_SHIPPING => 'Pay for the shipping of a return',
            CreditTransactionReasonEnum::COMPENSATE_CUSTOMER => 'Compensate customer',
            CreditTransactionReasonEnum::TRANSFER => 'Transfer from other customer account',
            CreditTransactionReasonEnum::MONEY_BACK => 'Customer want money back',
            CreditTransactionReasonEnum::OTHER => 'Other reason',
        };
    }

    public static function getDecreaseReasons(): array
    {
        return [
            [
                'value' => self::TRANSFER->value,
                'label' => self::TRANSFER->label(),
            ],
            [
                'value' => self::MONEY_BACK->value,
                'label' => self::MONEY_BACK->label(),
            ],
            [
                'value' => self::OTHER->value,
                'label' => self::OTHER->label(),
            ],
        ];
    }

    public static function getIncreaseReasons(): array
    {
        return [
            [
                'value' => self::PAY_FOR_SHIPPING->value,
                'label' => self::PAY_FOR_SHIPPING->label(),
            ],
            [
                'value' => self::COMPENSATE_CUSTOMER->value,
                'label' => self::COMPENSATE_CUSTOMER->label(),
            ],
            [
                'value' => self::TRANSFER->value,
                'label' => self::TRANSFER->label(),
            ],
            [
                'value' => self::OTHER->value,
                'label' => self::OTHER->label(),
            ],
        ];
    }


}
