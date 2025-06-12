<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Sep 2023 14:12:54 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\UI\Dashboard;

use App\Actions\RetinaAction;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowRetinaDashboard extends RetinaAction
{
    use AsAction;


    public function handle(ActionRequest $request): Response
    {
        $inertiaPage = 'Dashboard/RetinaFulfilmentDashboard';
        if ($this->shop->type === ShopTypeEnum::DROPSHIPPING) {
            $inertiaPage = 'Dashboard/RetinaDropshippingDashboard';
        } elseif ($this->shop->type === ShopTypeEnum::B2B) {
            $inertiaPage = 'Dashboard/RetinaB2BDashboard';
        }

        if ($this->asPupil) {
            $inertiaPage = 'Dashboard/PupilFulfilmentDashboard';
        }

        return Inertia::render(
            $inertiaPage,
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'data'        => match ($this->shop->type) {
                    ShopTypeEnum::FULFILMENT => GetRetinaFulfilmentHomeData::run($this->fulfilmentCustomer, $request),
                    ShopTypeEnum::DROPSHIPPING => GetRetinaDropshippingHomeData::run($this->customer),
                    ShopTypeEnum::B2B => GetRetinaB2BHomeData::run($this->customer, $request),
                    default => []
                },
            ]
        );
    }

    public function asController(ActionRequest $request): Response
    {
        $this->initialisation($request);

        return $this->handle($request);
    }

    public function inPupil(ActionRequest $request): Response
    {
        $this->initialisationFromPupil($request);

        return $this->handle($request);
    }

    public function getBreadcrumbs(): array
    {
        return [


        ];
    }
}
