<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 Oct 2025 13:30:03 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Iris\Json;

use App\Actions\RetinaAction;
use App\Actions\Traits\HasIrisUserData;
use App\Actions\Traits\WithIrisAuthCookie;
use App\Models\Catalogue\Collection;
use Lorisleiva\Actions\ActionRequest;

class GetRetinaFirstHitData extends RetinaAction
{
    use HasIrisUserData;
    use WithIrisAuthCookie;

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function handle(): array
    {

        if (auth()->check()) {
            $this->queueIrisAuthCookie();
        } else {
            $this->forgetIrisAuthCookie();
        }

        return $this->getIrisUserData();
    }


    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function asController(Collection $collection, ActionRequest $request): \Illuminate\Http\Response|array
    {
        $this->initialisation($request);

        return $this->handle();
    }


}
