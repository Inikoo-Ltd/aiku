<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 Oct 2025 13:30:03 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Iris\Json;

use App\Actions\Iris\RetinaLogWebUserRequest;
use App\Actions\RetinaAction;
use App\Actions\Traits\HasIrisUserData;
use App\Models\Catalogue\Collection;
use Illuminate\Support\Facades\Cookie;
use Lorisleiva\Actions\ActionRequest;

class GetRetinaFirstHitData extends RetinaAction
{
    use HasIrisUserData;
    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function handle(): array
    {

        RetinaLogWebUserRequest::run();
        Cookie::queue('iris_vua', true, config('session.lifetime') * 60);
        return $this->getIrisUserData();
    }


    // getIrisUserData moved to HasIrisUserData trait

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
