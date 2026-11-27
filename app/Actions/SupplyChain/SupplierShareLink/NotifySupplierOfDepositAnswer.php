<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-27 16:30
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\SupplyChain\SupplierShareLink;

use App\Actions\SysAdmin\User\NotifyUserOnChosenChannels;
use App\Enums\SysAdmin\User\UserNotificationEnum;
use App\Models\Helpers\Language;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierPortalContact;
use App\Models\SysAdmin\User;
use App\Notifications\SupplierDepositAnsweredNotification;
use App\Notifications\SupplierDepositRequestedNotification;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Traits\Localizable;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Emails the supplier what happened to the deposit it asked for from its portal, each contact with a fresh private
 * link to its page, where the order shows the same message. The contact who asked is told; when it lost its access
 * every contact of the supplier is. A paid deposit is told once, when the deposit stage is first reached. On an agent
 * order the agent's purchasing team is told the same answer.
 */
class NotifySupplierOfDepositAnswer
{
    use AsObject;
    use Localizable;

    public const int LINK_DAYS = 7;

    /**
     * @var array<int, string>
     */
    private array $languageCodes = [];

    public function paid(PurchaseOrder $purchaseOrder): int
    {
        $request = Arr::get($purchaseOrder->data ?? [], 'supplier_deposit_request');
        if (!$request || isset($request['paid_notified_at']) || !$purchaseOrder->isDepositPaid()) {
            return 0;
        }

        $data = $purchaseOrder->data;
        $data['supplier_deposit_request']['paid_notified_at'] = now()->toIso8601String();
        $purchaseOrder->update(['data' => $data]);

        $sent = $this->send(
            $purchaseOrder,
            $request,
            __(':reference: deposit paid', ['reference' => $purchaseOrder->reference]),
            __('Deposit paid, please add your production dates to take us to the next stage.')
        );

        $this->tellAgent($purchaseOrder, fn (string $supplier) => [
            __(':reference: deposit paid to :supplier', ['reference' => $purchaseOrder->reference, 'supplier' => $supplier]),
            __('The :percentage% deposit :supplier asked for on :reference is paid. :supplier was asked to add its production dates.', ['percentage' => $request['percentage'], 'supplier' => $supplier, 'reference' => $purchaseOrder->reference]),
        ]);

        return $sent;
    }

    /**
     * @param  array{percentage: int|float, amount: float, supplier_portal_contact_id?: int|null}  $request
     */
    public function rejected(PurchaseOrder $purchaseOrder, array $request, string $reason): int
    {
        $sent = $this->send(
            $purchaseOrder,
            $request,
            __(':reference: deposit request rejected', ['reference' => $purchaseOrder->reference]),
            __('Your deposit request has been rejected because :reason. Please resubmit to continue.', ['reason' => rtrim($reason, '. ')])
        );

        $this->tellAgent($purchaseOrder, fn (string $supplier) => [
            __(':reference: deposit request of :supplier rejected', ['reference' => $purchaseOrder->reference, 'supplier' => $supplier]),
            __('The :percentage% deposit :supplier asked for on :reference was rejected because :reason. :supplier was asked to resubmit.', ['percentage' => $request['percentage'], 'supplier' => $supplier, 'reference' => $purchaseOrder->reference, 'reason' => rtrim($reason, '. ')]),
        ]);

        return $sent;
    }

    /**
     * On an agent order the agent's purchasing team, who were told of the request, are told of the answer too, in
     * their own language, on the bell and by email as they chose for deposit requests.
     *
     * @param  callable(string): array{0: string, 1: string}  $texts
     */
    private function tellAgent(PurchaseOrder $purchaseOrder, callable $texts): int
    {
        if (!$purchaseOrder->isAgentOrder()) {
            return 0;
        }

        $supplierName = $this->supplierOf($purchaseOrder)?->officialName() ?? '';
        $recipients   = NotifyPurchasingOfSupplierDepositRequest::make()->recipients($purchaseOrder)->where('forAgent', true);

        foreach ($recipients as ['user' => $user, 'route' => $route]) {
            $this->withLocale($this->userLocale($user), function () use ($purchaseOrder, $texts, $supplierName, $user, $route) {
                [$title, $body] = $texts($supplierName);

                NotifyUserOnChosenChannels::run(
                    $user,
                    UserNotificationEnum::PURCHASE_ORDER_DEPOSIT_REQUESTED,
                    new SupplierDepositRequestedNotification(
                        $purchaseOrder,
                        $title,
                        $body,
                        __('You get this email because you handle the purchase orders of the agent of this order.'),
                        $route,
                        'supplier_deposit_answered'
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
        return $this->languageCodes[$user->language_id] ??= Language::find($user->language_id)?->code ?: config('app.locale');
    }

    /**
     * @param  array{supplier_portal_contact_id?: int|null}  $request
     */
    private function send(PurchaseOrder $purchaseOrder, array $request, string $title, string $body): int
    {
        $purchaseOrder->loadMissing('group');
        $contacts = $this->recipients($purchaseOrder, $request['supplier_portal_contact_id'] ?? null);

        foreach ($contacts as $contact) {
            $link = StoreSupplierShareLink::make()->handle($contact->supplier, [
                'days'                       => self::LINK_DAYS,
                'supplier_portal_contact_id' => $contact->id,
            ]);

            Notification::route('mail', $contact->email)->notify(
                new SupplierDepositAnsweredNotification($title, $body, $link['url'], $purchaseOrder->group->name)
            );
        }

        return $contacts->count();
    }

    /**
     * @return Collection<int, SupplierPortalContact>
     */
    public function recipients(PurchaseOrder $purchaseOrder, ?int $requesterId): Collection
    {
        $requester = $requesterId ? SupplierPortalContact::authorised()->with('supplier')->find($requesterId) : null;
        if ($requester) {
            return collect([$requester]);
        }

        $supplier = $this->supplierOf($purchaseOrder);
        if (!$supplier) {
            return collect();
        }

        return SupplierPortalContact::authorised()->where('supplier_id', $supplier->id)->with('supplier')->get();
    }

    private function supplierOf(PurchaseOrder $purchaseOrder): ?Supplier
    {
        if ($purchaseOrder->supplier_id) {
            return Supplier::find($purchaseOrder->supplier_id);
        }

        return $purchaseOrder->parent instanceof OrgSupplier ? $purchaseOrder->parent->supplier : null;
    }
}
