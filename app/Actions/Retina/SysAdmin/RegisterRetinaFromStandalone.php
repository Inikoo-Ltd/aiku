<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 02-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\SysAdmin;

use App\Actions\IrisAction;
use Lorisleiva\Actions\ActionRequest;

class RegisterRetinaFromStandalone extends IrisAction
{
    use WithRetinaRegistration;

    /**
     * @throws \Throwable
     */
    public function asController(ActionRequest $request): void
    {
        $this->initialisation($request);
        $this->handle($this->validatedData);

    }
}
