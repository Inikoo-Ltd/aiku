<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-17 10:00
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
use App\Models\SysAdmin\User;
use App\Notifications\SupplierDepositRequestedNotification;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Traits\Localizable;
use Lorisleiva\Actions\Concerns\AsAction;
use NumberFormatter;
use Spatie\Permission\Models\Permission;

/**
 * Tells the order's buyer, David Hardy, Tomas Belan and the purchasing leads of the ordering organisation, as for a price change, plus the
 * agent's purchasing leads and staff who edit its orders, when the supplier asks for a deposit from its portal. Agent
 * users open the agent's copy of the order. The portal has no signed-in user, so nothing set the permission team:
 * the order's group is set before reading roles, or role-held permissions (agent manager) find nobody.
 */
class NotifyPurchasingOfSupplierDepositRequest
{
    use AsAction;
    use Localizable;

    /**
     * David Hardy and Tomas Belan are told of every deposit request, whatever their roles (Vika, 25 Nov 2026).
     */
    public const array ALWAYS_TOLD_USERNAMES = ['david', 'tomas'];

    /**
     * @var array<int, string>
     */
    private array $languageCodes = [];

    public function handle(PurchaseOrder $purchaseOrder): int
    {
        $purchaseOrder->loadMissing(['organisation', 'currency', 'agent.organisation']);
        $request = Arr::get($purchaseOrder->data ?? [], 'supplier_deposit_request');
        if (!$request) {
            return 0;
        }

        $recipients = $this->recipients($purchaseOrder);

        foreach ($recipients as ['user' => $user, 'route' => $route, 'forAgent' => $forAgent]) {
            $this->withLocale($this->userLocale($user), function () use ($purchaseOrder, $request, $user, $route, $forAgent) {
                $supplierName = $this->supplierOf($purchaseOrder)?->officialName() ?? '';
                $title        = __(':reference: :supplier asks for a :percentage% deposit', [
                    'reference'  => $purchaseOrder->reference,
                    'supplier'   => $supplierName,
                    'percentage' => $request['percentage'],
                ]);
                $body         = __(':supplier asks for a deposit of :amount (:percentage% of the order) before starting production of :reference.', [
                    'supplier'   => $supplierName,
                    'amount'     => $this->money((float) $request['amount'], $purchaseOrder->currency->code),
                    'percentage' => $request['percentage'],
                    'reference'  => $purchaseOrder->reference,
                ]);
                $why          = $forAgent
                    ? __('You get this email because you handle the purchase orders of the agent of this order.')
                    : __('You get this email because you are the buyer of this order or lead purchasing in its organisation.');

                NotifyUserOnChosenChannels::run(
                    $user,
                    UserNotificationEnum::PURCHASE_ORDER_DEPOSIT_REQUESTED,
                    new SupplierDepositRequestedNotification($purchaseOrder, $title, $body, $why, $route),
                    $title,
                    $body,
                    route($route['name'], $route['parameters'])
                );
            });
        }

        return $recipients->count();
    }

    private function supplierOf(PurchaseOrder $purchaseOrder): ?Supplier
    {
        if ($purchaseOrder->supplier_id) {
            return Supplier::find($purchaseOrder->supplier_id);
        }

        return $purchaseOrder->parent instanceof OrgSupplier ? $purchaseOrder->parent->supplier : null;
    }

    /**
     * Who is told, each with the copy of the order they open.
     *
     * @return Collection<int, array{user: User, route: array{name: string, parameters: list<string>}, forAgent: bool}>
     */
    public function recipients(PurchaseOrder $purchaseOrder): Collection
    {
        $purchaseOrder->loadMissing(['organisation', 'agent.organisation']);
        setPermissionsTeamId($purchaseOrder->group_id);

        $agentOrganisation = $purchaseOrder->isAgentOrder() ? $purchaseOrder->agent?->organisation : null;
        $clientUsers       = $this->users(["org-supervisor.$purchaseOrder->organisation_id.procurement"], $purchaseOrder->buyer_id);
        $clientUsers       = $clientUsers->concat(
            User::where('group_id', $purchaseOrder->group_id)->where('status', true)->whereIn('username', self::ALWAYS_TOLD_USERNAMES)->whereNotIn('id', $clientUsers->pluck('id'))->get()
        );
        $agentUsers        = $agentOrganisation
            ? $this->users(["org-supervisor.$agentOrganisation->id.procurement", "procurement.$agentOrganisation->id.edit"], null)->whereNotIn('id', $clientUsers->pluck('id'))
            : collect();

        $clientRoute = ['name' => 'grp.org.procurement.purchase_orders.show', 'parameters' => [$purchaseOrder->organisation->slug, $purchaseOrder->slug]];
        $agentRoute  = $agentOrganisation ? ['name' => 'grp.org.agent.purchase_orders.show', 'parameters' => [$agentOrganisation->slug, $purchaseOrder->slug]] : null;

        return $clientUsers->map(fn (User $user) => ['user' => $user, 'route' => $clientRoute, 'forAgent' => false])
            ->concat($agentUsers->map(fn (User $user) => ['user' => $user, 'route' => $agentRoute, 'forAgent' => true]))
            ->values();
    }

    /**
     * The people who get the request by email: they have an address and keep email on for this notification. The
     * supplier sees this list, so it carries names only, never the addresses.
     *
     * @return list<array{name: string, organisation: string}>
     */
    public function emailRecipients(PurchaseOrder $purchaseOrder): array
    {
        return $this->recipients($purchaseOrder)
            ->filter(fn (array $recipient) => filled($recipient['user']->email)
                && in_array('email', UserNotificationEnum::PURCHASE_ORDER_DEPOSIT_REQUESTED->channelsFor($recipient['user']), true))
            ->map(fn (array $recipient) => [
                'name'         => $recipient['user']->contact_name ?: $recipient['user']->username,
                'organisation' => $recipient['forAgent'] ? $purchaseOrder->agent->organisation->name : $purchaseOrder->organisation->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $permissionNames
     * @return Collection<int, User>
     */
    private function users(array $permissionNames, ?int $buyerId): Collection
    {
        $permissions = Permission::whereIn('name', $permissionNames)->pluck('name')->all();
        if (!$permissions && !$buyerId) {
            return collect();
        }

        return User::where('status', true)
            ->where(fn ($query) => $query
                ->when($buyerId, fn ($query) => $query->where('users.id', $buyerId))
                ->when($permissions, fn ($query) => $query->orWhereIn('users.id', User::permission($permissions)->select('users.id'))))
            ->get();
    }

    private function userLocale(User $user): string
    {
        return $this->languageCodes[$user->language_id] ??= Language::find($user->language_id)?->code ?: config('app.locale');
    }

    private function money(float $amount, string $currencyCode): string
    {
        return (new NumberFormatter(app()->getLocale(), NumberFormatter::CURRENCY))->formatCurrency($amount, $currencyCode);
    }
}
