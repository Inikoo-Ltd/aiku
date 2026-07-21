<?php

/*
 * @Author: andiferdiawan (https://github.com/andiferdiawan)
 * @Created: YYYY-MM-DD HH:mm:ss
 * @Copyright: Copyright (c) 2026, andiferdiawan
 */

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 21 Jul 2026 15:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\ClockingMachine\UI;

use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\Concerns\AsAction;

class RedirectClockingMachineQrScan
{
    use AsAction;

    /**
     * Landing point for a clocking QR scanned with a phone's native camera.
     */
    public function asController(string $hash): RedirectResponse
    {
        return redirect()->route('grp.clocking_employees.index');
    }
}
