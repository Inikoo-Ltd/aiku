<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 24 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Exceptions;

use Illuminate\Validation\ValidationException;

/**
 * Checkout.com may have made the refund (no answer, or an answer without a reference): nothing is recorded in aiku,
 * and no more money must be sent from the same balance until someone has checked Checkout.com.
 */
class CheckoutRefundOutcomeUnknown extends ValidationException
{
}
