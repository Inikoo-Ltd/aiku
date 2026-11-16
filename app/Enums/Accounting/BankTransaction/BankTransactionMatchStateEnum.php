<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026 09:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Accounting\BankTransaction;

use App\Enums\EnumHelperTrait;

enum BankTransactionMatchStateEnum: string
{
    use EnumHelperTrait;

    case MATCHED = 'matched';
    case ALREADY_PAID = 'already_paid';
    case REVIEW = 'review';
    case UNMATCHED = 'unmatched';
    case DISMISSED = 'dismissed';
    case PAID_BY_MARKETPLACE = 'paid_by_marketplace';
    case CARD_PROCESSOR_PAYOUT = 'card_processor_payout';
}
