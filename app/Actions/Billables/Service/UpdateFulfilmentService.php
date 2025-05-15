<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 14 May 2025 19:12:31 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Billables\Service;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Billables\Service;
use Lorisleiva\Actions\ActionRequest;

class UpdateFulfilmentService extends OrgAction
{
    use WithActionUpdate;
    use WithControllerUpdateServiceRules;


    private Service $service;

    public function handle(Service $service, array $modelData): Service
    {
        return UpdateService::run($service, $modelData);
    }


    public function asController(Service $service, ActionRequest $request): Service
    {
        $this->service = $service;
        $this->initialisationFromFulfilment($service->shop->fulfilment, $request);

        return $this->handle($service, $this->validatedData);
    }




}
