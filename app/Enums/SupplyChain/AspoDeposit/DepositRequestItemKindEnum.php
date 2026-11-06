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
 * that order before it ships, or enough to bring the client's wallet with the agent back to zero.
 */
enum DepositRequestItemKindEnum: string
{
    use EnumHelperTrait;

    case ADVANCE = 'advance';
    case BALANCE = 'balance';
    case WALLET = 'wallet';

    public static function labels(): array
    {
        return [
            'advance' => __('Advance'),
            'balance' => __('Balance'),
            'wallet'  => __('Cover the negative balance'),
        ];
    }

    public function describeForPurchaseOrder(): string
    {
        return match ($this) {
            self::ADVANCE => __('Advance payment for PO'),
            self::BALANCE => __('Balance payment for PO'),
            self::WALLET  => self::labels()[$this->value],
        };
    }
}
