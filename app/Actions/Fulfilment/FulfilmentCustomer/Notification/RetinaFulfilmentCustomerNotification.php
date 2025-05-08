<?php

/*
 * author Arya Permana - Kirin
 * created on 08-05-2025-09h-58m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Fulfilment\FulfilmentCustomer\Notification;

use App\Enums\Fulfilment\PalletReturn\PalletReturnTypeEnum;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Fulfilment\PalletReturn;

class RetinaFulfilmentCustomerNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected PalletDelivery|PalletReturn $parent;

    public function __construct(PalletDelivery|PalletReturn $parent)
    {
        $this->parent = $parent;
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        $state = $this->parent->state;
        $notificationData = $state->notifications($this->parent->reference)[$state->value];

        $route = null;

        if ($this->parent instanceof PalletDelivery) {
            $route = route('retina.fulfilment.storage.pallet_deliveries.show', [
                'palletDelivery' => $this->parent->slug
            ]);
        } elseif ($this->parent instanceof PalletReturn) {
            if ($this->parent->type == PalletReturnTypeEnum::PALLET) {
                $route = route('retina.fulfilment.storage.pallet_returns.show', [
                    'palletReturn' => $this->parent->slug
                ]);
            } elseif ($this->parent->type == PalletReturnTypeEnum::STORED_ITEM) {
                $route = route('retina.fulfilment.storage.pallet_returns.with-stored-items.show', [
                    'palletReturn' => $this->parent->slug
                ]);
            }
        }

        return [
            'title' => $notificationData['title'],
            'body'  => $notificationData['subtitle'],
            'type'  => class_basename($this->parent),
            'slug'  => $this->parent->slug,
            'id'    => $this->parent->id,
            'route' => $route,
        ];
    }
}
