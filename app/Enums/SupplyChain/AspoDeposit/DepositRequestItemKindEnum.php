<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 19 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\SupplyChain\AspoDeposit;

use App\Enums\EnumHelperTrait;

/**
 * What a line of an agent's money request asks the client for: the advance on a supplier order, the rest of
 * that order before it ships, the charges the agent paid for one shipment, or one expense of the ledger. A credit
 * line takes off the client's money the agent still holds, so it is the only line with a negative amount. Wallet
 * lines asked for the whole negative balance at once; only requests made before the expense lines carry them.
 */
enum DepositRequestItemKindEnum: string
{
    use EnumHelperTrait;

    case ADVANCE = 'advance';
    case BALANCE = 'balance';
    case SHIPMENT = 'shipment';
    case EXPENSE = 'expense';
    case CREDIT = 'credit';
    case WALLET = 'wallet';

    public static function labels(): array
    {
        return [
            'advance'  => __('Advance'),
            'balance'  => __('Balance'),
            'shipment' => __('Shipment costs'),
            'expense'  => __('Expense'),
            'credit'   => __('Client money already with the agent'),
            'wallet'   => __('Cover the negative balance'),
        ];
    }

    public function describeForPurchaseOrder(): string
    {
        return match ($this) {
            self::ADVANCE => __('Advance payment for PO'),
            self::BALANCE => __('Balance payment for PO'),
            default       => self::labels()[$this->value],
        };
    }

    /**
     * @return list<string>
     */
    public static function requestableValues(): array
    {
        return [self::ADVANCE->value, self::BALANCE->value, self::SHIPMENT->value, self::EXPENSE->value];
    }
}
