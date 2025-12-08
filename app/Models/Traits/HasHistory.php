<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 12 Jun 2023 13:36:29 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\Traits;

use OwenIt\Auditing\Auditable;

trait HasHistory
{
    use Auditable;
}
