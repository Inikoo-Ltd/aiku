<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 14 Oct 2025 11:43:46 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Iris;

use App\Actions\IrisAction;
use App\Actions\Retina\SysAdmin\ProcessRetinaWebUserRequest;
use App\Actions\Traits\CanLogWebUserRequest;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class IrisLogWebUserRequest extends IrisAction
{
    use AsAction;
    use CanLogWebUserRequest;


    public function handle(): void
    {
        if ($this->canLogWebUserRequest()) {
            ProcessRetinaWebUserRequest::dispatch(
                request()->user(),
                now(),
                [
                    'name'      => request()->route()->getName(),
                    'arguments' => request()->route()->originalParameters(),
                    'url'       => request()->path(),
                ],
                request()->ip(),
                request()->header('User-Agent')
            );
        }
    }


    public function asController(ActionRequest $request): void
    {
        $this->initialisation($request);


        $this->handle();
    }



}
