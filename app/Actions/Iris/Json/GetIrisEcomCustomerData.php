<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 13 Oct 2025 14:30:56 Central Indonesia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Iris\Json;

use App\Actions\RetinaAction;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Collection;
use Lorisleiva\Actions\ActionRequest;

class GetIrisEcomCustomerData extends RetinaAction
{
    public function handle(): array
    {
        if (!isset($this->webUser) || !$this->webUser) {
            return [
                'is_logged_in' => false,
            ];
        }

        return $this->getIrisUserData();
    }

    protected function getIrisUserData(): array
    {
        $webUser = $this->webUser;
        $customer = $this->customer ?? null;
        $shop = $this->shop ?? null;

        $cartCount  = 0;
        $cartAmount = 0;

        if ($shop && $shop->type === ShopTypeEnum::B2B && $customer?->orderInBasket) {
            $orderInBasket = $customer->orderInBasket;
            $cartCount     = $orderInBasket->number_item_transactions ?? 0;
            $cartAmount    = $orderInBasket->total_amount ?? 0;
        }

        return [
            'is_logged_in' => true,
            'variables' => [
                'reference'        => $customer?->reference ?? '',
                'name'             => $webUser->contact_name ?? '',
                'username'         => $webUser->username ?? '',
                'email'            => $webUser->email ?? '',
                'favourites_count' => $customer?->stats?->number_favourites ?? 0,
                'cart_count'       => $cartCount,
                'cart_amount'      => $cartAmount,
            ],
        ];
    }

    public function asController(Collection $collection, ActionRequest $request): \Illuminate\Http\Response|array
    {
        $this->initialisation($request);
        $this->webUser ??= auth()->user();
        $this->shop ??= $collection->shop ?? null;
        $this->customer ??= $this->webUser?->customer ?? null;

        return $this->handle();
    }
}
