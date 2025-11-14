<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 20 Oct 2025 09:48:34 Central Indonesia Time, Canggu, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Iris\Json;

use App\Actions\Iris\CaptureTrafficSource;
use App\Actions\Iris\RetinaLogWebUserRequest;
use App\Actions\IrisAction;
use App\Actions\Traits\HasIrisUserData;
use App\Models\Catalogue\Collection;
use Illuminate\Support\Facades\Cookie;
use Lorisleiva\Actions\ActionRequest;

class GetIrisFirstHitData extends IrisAction
{
    use HasIrisUserData;



    private ?\App\Models\Fulfilment\Fulfilment $fulfilment;
    private null $fulfilmentCustomer;
    /**
     * @var \App\Models\SysAdmin\User|mixed
     */
    private mixed $webUser;
    /**
     * @var mixed|null
     */
    private mixed $customer;

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function handle(): array
    {

        if (auth()->check()) {
            $this->webUser = request()->user();
            $this->customer = $this->webUser?->customer;
            $this->fulfilmentCustomer = $this->customer?->fulfilmentCustomer;
            $this->shop = $this->customer?->shop;
            $this->fulfilment = $this->shop->fulfilment;
            $this->website = request()->get('website');
            $this->organisation = $this->website->organisation;

            RetinaLogWebUserRequest::run();
            Cookie::queue('iris_vua', true, config('session.lifetime') * 60);
            return $this->getIrisUserData();
        } else {
            return [
                'is_logged_in' => false,
                'traffic_source_cookies' => CaptureTrafficSource::run(),
            ];
        }


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
