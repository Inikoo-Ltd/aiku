<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Thu, 13 Jan 2022 15:13:28 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Inikoo
 *  Version 4.0
 */

namespace App\Actions\UI;

use Lorisleiva\Actions\Concerns\WithAttributes;

trait WithInertia
{
    use WithAttributes;

    /**
     * @throws \Exception
     */
    public function getValidationFailure(): void
    {
        abort(422);
    }
}
