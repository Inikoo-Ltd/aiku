<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-27 10:00
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\SupplyChain\SupplierShareLink;

use App\Actions\SysAdmin\User\NotifyUserOnChosenChannels;
use App\Enums\SysAdmin\User\UserNotificationEnum;
use App\Models\Helpers\Language;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\SupplierOrderProposal;
use App\Models\SysAdmin\User;
use App\Notifications\SupplierOrderProposalNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Traits\Localizable;
use Lorisleiva\Actions\Concerns\AsAction;
use NumberFormatter;
use Spatie\Permission\Models\Permission;

/**
 * Tells the purchasing leads of the organisation, David Hardy and Tomas Belan, that the supplier proposed lines to
 * order from its portal. They open the Shopping page the lines wait on: the supplier's own, or its agent's for a
 * supplier through an agent. The portal has no signed-in user, so the permission team is set before reading roles.
 */
class NotifyPurchasingOfSupplierOrderProposal
{
    use AsAction;
    use Localizable;

    /**
     * @param  Collection<int, SupplierOrderProposal>  $proposals  lines of one org supplier
     */
    public function handle(Collection $proposals): int
    {
        /** @var SupplierOrderProposal|null $first */
        $first = $proposals->first();
        if (!$first) {
            return 0;
        }

        $orgSupplier = $first->orgSupplier()->with(['organisation', 'supplier.currency', 'orgAgent'])->first();
        $recipients  = $this->recipients($orgSupplier);
        $route       = self::shoppingRoute($orgSupplier);
        $value       = $proposals->sum(fn (SupplierOrderProposal $proposal) => (float) $proposal->quantity_units * (float) $proposal->unit_cost);

        foreach ($recipients as $user) {
            $this->withLocale($this->userLocale($user), function () use ($orgSupplier, $proposals, $value, $user, $route) {
                $supplierName = $orgSupplier->supplier->officialName();
                $title        = __(':supplier proposes :count lines to order', ['supplier' => $supplierName, 'count' => $proposals->count()]);
                $body         = __(':supplier proposes :count lines worth :amount for :organisation: products out of stock or that will run out before a new order can arrive. Accept them onto the draft purchase order or reject them.', [
                    'supplier'     => $supplierName,
                    'count'        => $proposals->count(),
                    'amount'       => $this->money($value, $orgSupplier->supplier->currency?->code ?? 'GBP'),
                    'organisation' => $orgSupplier->organisation->name,
                ]);

                NotifyUserOnChosenChannels::run(
                    $user,
                    UserNotificationEnum::SUPPLIER_ORDER_PROPOSAL,
                    new SupplierOrderProposalNotification(
                        $orgSupplier,
                        $title,
                        $body,
                        __('You get this email because you lead purchasing in this organisation.'),
                        $route
                    ),
                    $title,
                    $body,
                    route($route['name'], $route['parameters'])
                );
            });
        }

        return $recipients->count();
    }

    /**
     * @return array{name: string, parameters: list<string>}
     */
    public static function shoppingRoute(OrgSupplier $orgSupplier): array
    {
        return $orgSupplier->orgAgent
            ? ['name' => 'grp.org.procurement.org_agents.show.shopping.dashboard', 'parameters' => [$orgSupplier->organisation->slug, $orgSupplier->orgAgent->slug]]
            : ['name' => 'grp.org.procurement.org_suppliers.show.shopping.dashboard', 'parameters' => [$orgSupplier->organisation->slug, $orgSupplier->slug]];
    }

    /**
     * @return Collection<int, User>
     */
    public function recipients(OrgSupplier $orgSupplier): Collection
    {
        setPermissionsTeamId($orgSupplier->group_id);

        $permissions = Permission::whereIn('name', ["org-supervisor.$orgSupplier->organisation_id.procurement"])->pluck('name')->all();
        $leads       = $permissions
            ? User::where('status', true)->whereIn('id', User::permission($permissions)->select('users.id'))->get()
            : collect();

        return $leads
            ->concat(User::where('group_id', $orgSupplier->group_id)->where('status', true)->whereIn('username', NotifyPurchasingOfSupplierDepositRequest::ALWAYS_TOLD_USERNAMES)->whereNotIn('id', $leads->pluck('id'))->get())
            ->filter(fn (User $user) => $user->authTo("procurement.$orgSupplier->organisation_id.view"))
            ->values();
    }

    private function userLocale(User $user): string
    {
        return Language::find($user->language_id)?->code ?: config('app.locale');
    }

    private function money(float $amount, string $currencyCode): string
    {
        return (new NumberFormatter(app()->getLocale(), NumberFormatter::CURRENCY))->formatCurrency($amount, $currencyCode);
    }
}
