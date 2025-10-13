<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 13 Oct 2025 14:30:56 Central Indonesia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Iris\Json;

use App\Actions\RetinaAction;
use App\Enums\Catalogue\Shop\ShopTypeEnum;

class GetIrisEcomCustomerData extends RetinaAction
{
    public function handle(): array
    {
        if (!$this->webUser) {
            return [
                'is_logged_in' => false,
            ];
        }


        $cartCount  = 0;
        $cartAmount = 0;

        if ($this->shop->type == ShopTypeEnum::B2B) {
            $orderInBasket = $this->customer->orderInBasket;
            $cartCount     = $orderInBasket ? $orderInBasket->number_item_transactions : 0;
            $cartAmount    = $orderInBasket ? $orderInBasket->total_amount : 0;
        }

        return [

            'is_logged_in' => true,
            'variables'    => [
                'favourites_count' => $this->customer->stats->number_favourites,
                'cart_count'       => $cartCount,  // Count of unique items
                'cart_amount'      => $cartAmount,
            ],
        ];

    }



}
