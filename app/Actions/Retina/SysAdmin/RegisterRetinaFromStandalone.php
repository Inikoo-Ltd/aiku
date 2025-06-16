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
use App\Actions\CRM\Customer\RegisterCustomer;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

class RegisterRetinaFromStandalone extends IrisAction
{
    use WithRetinaRegistration;

    public function handle(array $modelData): void
    {
        RegisterCustomer::run(
            $this->shop,
            $modelData
        );
    }

    public function authorize(ActionRequest $request): bool
    {
        return true;
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
