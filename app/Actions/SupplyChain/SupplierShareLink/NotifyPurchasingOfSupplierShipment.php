<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Nov 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierShareLink;

use App\Actions\SysAdmin\User\NotifyUserOnChosenChannels;
use App\Enums\SysAdmin\User\UserNotificationEnum;
use App\Models\Helpers\Language;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SysAdmin\User;
use App\Notifications\SupplierDepositRequestedNotification;
use Illuminate\Support\Traits\Localizable;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A direct supplier marked from its portal that it shipped the order: the buyer and the purchasing leads of the
 * ordering organisation are told, with a link to the order and its documents. Suppliers often skip the portal, so
 * nothing waits for this: goods in receives the parcel against the order whether or not it came (INI-523).
 */
class NotifyPurchasingOfSupplierShipment
{
    use AsAction;
    use Localizable;

    public function handle(PurchaseOrder $purchaseOrder): int
    {
        $recipients = NotifyPurchasingOfSupplierDepositRequest::make()->recipients($purchaseOrder)
            ->reject(fn (array $recipient) => $recipient['forAgent']);

        $supplierName = $purchaseOrder->parent_name;

        foreach ($recipients as ['user' => $user, 'route' => $route]) {
            $this->withLocale($this->userLocale($user), function () use ($purchaseOrder, $supplierName, $user, $route) {
                $title = __(':reference: :supplier shipped the order', ['reference' => $purchaseOrder->reference, 'supplier' => $supplierName]);
                $body  = __(':supplier marked :reference as shipped from its portal. Its shipping documents are on the order.', [
                    'supplier'  => $supplierName,
                    'reference' => $purchaseOrder->reference,
                ]);

                NotifyUserOnChosenChannels::run(
                    $user,
                    UserNotificationEnum::PURCHASE_ORDER_SHIPPED_BY_SUPPLIER,
                    new SupplierDepositRequestedNotification(
                        $purchaseOrder,
                        $title,
                        $body,
                        __('You get this email because you are the buyer of this order or lead purchasing in its organisation.'),
                        $route,
                        'supplier_shipped_order'
                    ),
                    $title,
                    $body,
                    route($route['name'], $route['parameters'])
                );
            });
        }

        return $recipients->count();
    }

    private function userLocale(User $user): string
    {
        return Language::find($user->language_id)?->code ?: config('app.locale');
    }
}
