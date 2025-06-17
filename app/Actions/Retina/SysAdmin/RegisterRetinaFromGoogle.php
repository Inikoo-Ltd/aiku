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
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;

class RegisterRetinaFromGoogle extends IrisAction
{
    use WithRetinaRegistration;

    public function prepareForValidation(): void
    {
        $this->set('password', Str::random(24));
    }

    /**
     * @throws \Throwable
     */
    public function asController(ActionRequest $request): RedirectResponse
    {
        $this->initialisation($request);
        $this->handle($this->validatedData);

        return redirect()->route('retina.dashboard.show');
    }
}
