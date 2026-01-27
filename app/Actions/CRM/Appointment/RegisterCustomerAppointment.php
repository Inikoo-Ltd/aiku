<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 18 Sep 2023 18:48:13 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Appointment;

use App\Actions\CRM\Customer\Register;
use App\Models\Catalogue\Shop;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\AsCommand;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Throwable;

class RegisterCustomerAppointment
{
    use AsAction;
    use WithAttributes;
    use AsCommand;

    public function handle(Shop $shop, array $modelData): Authenticatable
    {
        Register::run($shop, $modelData);

        return Auth::guard('customer')->user();
    }

    /**
     * @throws Throwable
     */
    public function asController(ActionRequest $request): Authenticatable
    {
        $this->fillFromRequest($request);
        $request->validate();

        return $this->handle($request->input('website')->shop, $request->validated());
    }
}
