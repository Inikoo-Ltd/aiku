<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Notifications;

use App\Models\Catalogue\Shop;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ShopStockArrivalsNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private const int CODES_SHOWN = 25;

    /**
     * @param  array<int, string>  $newCodes
     * @param  array<int, string>  $backCodes
     */
    public function __construct(
        public Shop $shop,
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
            'title' => __(':count in stock · :shop', ['count' => count($this->newCodes) + count($this->backCodes), 'shop' => $this->shop->code]),
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
