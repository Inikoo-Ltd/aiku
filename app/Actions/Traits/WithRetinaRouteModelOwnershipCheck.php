<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 17 Sep 2026 17:00:00 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use App\Models\CRM\Customer;
use App\Models\CRM\WebUser;
use App\Models\Fulfilment\PalletStoredItem;
use App\Models\Helpers\Upload;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\PersonalAccessToken;
use Lorisleiva\Actions\ActionRequest;

trait WithRetinaRouteModelOwnershipCheck
{
    protected function retinaCustomerOwnsRouteModels(ActionRequest $request): bool
    {
        $customerId = $request->user()?->customer_id;

        if (!$customerId) {
            return false;
        }

        $fulfilmentCustomerId = $request->user()->customer?->fulfilmentCustomer?->id;

        return array_all(
            $request->route()->parameters(),
            fn ($parameter) => !$parameter instanceof Model
                || (in_array($this->ownerCustomerId($parameter), [null, $customerId], true)
                    && in_array($this->ownerFulfilmentCustomerId($parameter), [null, $fulfilmentCustomerId], true))
        );
    }

    private function ownerFulfilmentCustomerId(Model $model): ?int
    {
        return match (true) {
            $model instanceof PalletStoredItem                                  => $model->pallet?->fulfilment_customer_id ?? 0,
            array_key_exists('fulfilment_customer_id', $model->getAttributes()) => $model->fulfilment_customer_id ?? 0,
            default                                                             => null,
        };
    }

    private function ownerCustomerId(Model $model): ?int
    {
        return match (true) {
            $model instanceof Customer                              => $model->id,
            $model instanceof PersonalAccessToken                   => $model->tokenable ? $this->ownerCustomerId($model->tokenable) : 0,
            $model instanceof Upload                                => $model->customer_id ?? WebUser::find($model->web_user_id)?->customer_id ?? 0,
            array_key_exists('customer_id', $model->getAttributes()) => $model->customer_id ?? 0,
            default                                                 => null,
        };
    }
}
