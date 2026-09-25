<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Notifications;

use App\Models\Catalogue\Shop;
use Illuminate\Notifications\Notification;

class ShopStockArrivalsNotification extends Notification
{
    private const int CODES_SHOWN = 25;

    private const int SHOPS_SHOWN = 3;

    /**
     * @param  Shop  $shop  the shop the notification opens, the one with the most customers waiting
     * @param  array<int, string>  $shopCodes
     * @param  array<int, string>  $newCodes
     * @param  array<int, string>  $backCodes
     */
    public function __construct(
        public Shop $shop,
        public array $shopCodes,
        public array $newCodes,
        public array $backCodes,
        public int $waitingCustomers
    ) {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        $lines = [];
        if ($this->newCodes) {
            $lines[] = __('New in stock: :codes', ['codes' => $this->codeList($this->newCodes)]);
        }
        if ($this->backCodes) {
            $lines[] = __('Back in stock: :codes', ['codes' => $this->codeList($this->backCodes)]);
        }
        if ($this->waitingCustomers > 0) {
            $lines[] = trans_choice('{1} 1 customer asked to be told, the reminder email goes out by itself.|[2,*] :count customers asked to be told, the reminder emails go out by themselves.', $this->waitingCustomers);
        }

        return [
            'title' => __(':count in stock · :shop', ['count' => count($this->newCodes) + count($this->backCodes), 'shop' => $this->shopList()]),
            'body'  => implode("\n", $lines),
            'type'  => 'stock_arrivals',
            'slug'  => $this->shop->slug,
            'route' => [
                'name'       => 'grp.org.shops.show.catalogue.products.current_products.index',
                'parameters' => [
                    $this->shop->organisation->slug,
                    $this->shop->slug,
                ],
            ],
        ];
    }

    private function shopList(): string
    {
        $shown = implode(', ', array_slice($this->shopCodes, 0, self::SHOPS_SHOWN));
        $more  = count($this->shopCodes) - self::SHOPS_SHOWN;

        return $more > 0 ? $shown.' +'.$more : $shown;
    }

    /**
     * @param  array<int, string>  $codes
     */
    private function codeList(array $codes): string
    {
        $shown = implode(', ', array_slice($codes, 0, self::CODES_SHOWN));
        $more  = count($codes) - self::CODES_SHOWN;

        return $more > 0 ? __(':codes and :more more', ['codes' => $shown, 'more' => $more]) : $shown;
    }
}
