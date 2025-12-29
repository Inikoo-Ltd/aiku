<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 28 Dec 2025 11:46:59 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use Illuminate\Console\Command;

trait WithDiscountArgumentValidation
{
    protected function validateDiscountArgument(Command $command, string $argumentName = 'discount'): ?float
    {
        $discountArg = $command->argument($argumentName);

        if (! is_numeric($discountArg)) {
            $command->error('Invalid discount: must be numeric between 0 and 1 (e.g., 0.20 for 20%).');

            return null;
        }

        $discount = (float) $discountArg;

        if (! ($discount > 0 && $discount < 1)) {
            $command->error('Invalid discount: must be strictly between 0 and 1 (e.g., 0.20 for 20%).');

            return null;
        }

        return $discount;
    }
}
