<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 16 Oct 2025 17:13:17 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Enums\Discounts\OfferAllowance;

/**
 * Types of OfferAllowance effects.
 * Keep minimal to current usage; extend as needed.
 */
enum OfferAllowanceType: string
{
    case PERCENTAGE_OFF = 'percentage_off';
}
