<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 20 Nov 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\PartnerShippingList;

use App\Actions\Production\JobOrder\FillToOrder;
use App\Actions\SysAdmin\User\NotifyUserOnChosenChannels;
use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Enums\SysAdmin\User\UserNotificationEnum;
use App\Models\Ordering\Order;
use App\Models\Procurement\PartnerShoppingListItem;
use App\Models\Production\Artefact;
use App\Models\Production\Production;
use App\Models\SysAdmin\User;
use App\Notifications\ProductionShortfallNotification;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * HELP-4851: an order short of a product made in-house puts the missing SKOs on the To Produce board, where they wait for a
 * planner. The factory's floor supervisors are told at once, so the order is not first found short at picking.
 */
class NotifyProductionShortfall
{
    use AsAction;

    /**
     * @param  array<int, int>  $lineIds  the To Produce lines the order raised when it was submitted
     */
    public function handle(Order $order, array $lineIds): int
    {
        $lines = PartnerShoppingListItem::whereIn('id', $lineIds)
            ->where('state', ShoppingListItemStateEnum::OPEN)
            ->whereNull('job_order_id')
            ->with(['orgStock', 'transaction.model'])
            ->get()
            ->reject(fn (PartnerShoppingListItem $line) => FillToOrder::make()->artefactFor($line->transaction?->model) !== null);
        if ($lines->isEmpty()) {
            return 0;
        }

        $productions = Artefact::whereIn('org_stock_id', $lines->pluck('org_stock_id')->filter()->unique())
            ->where('organisation_id', $order->organisation_id)
            ->with('production.organisation')
            ->get()
            ->pluck('production', 'org_stock_id');

        $sent = 0;
        foreach ($lines->groupBy(fn (PartnerShoppingListItem $line) => $productions->get($line->org_stock_id)?->id) as $productionId => $productionLines) {
            $production = $productionLines->map(fn (PartnerShoppingListItem $line) => $productions->get($line->org_stock_id))->first();
            if (!$productionId || !$production) {
                continue;
            }

            $notification = new ProductionShortfallNotification($production, $order, $this->describe($productionLines));
            foreach ($this->supervisors($production) as $supervisor) {
                NotifyUserOnChosenChannels::run($supervisor, UserNotificationEnum::PRODUCTION_SHORTFALL, clone $notification, $notification->title(), $notification->body(), $notification->url());
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * @param  Collection<int, PartnerShoppingListItem>  $lines
     * @return array<int, string>
     */
    private function describe(Collection $lines): array
    {
        return $lines->map(fn (PartnerShoppingListItem $line) => sprintf('%s %s: %s', $line->orgStock?->code, $line->orgStock?->name, rtrim(rtrim(number_format((float) $line->quantity, 3, '.', ''), '0'), '.')))
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, User>
     */
    private function supervisors(Production $production): Collection
    {
        return User::where('status', true)
            ->whereHas('employees', fn ($query) => $query
                ->where('user_has_models.status', true)
                ->where('employees.organisation_id', $production->organisation_id)
                ->where('employees.state', EmployeeStateEnum::WORKING)
                ->whereHas('jobPositions', fn ($query) => $query->where('job_positions.code', 'prod-m')))
            ->get()
            ->filter(fn (User $user) => $user->authTo([
                'org-supervisor.'.$production->organisation_id,
                "productions_operations.$production->id",
                "productions_operations.$production->id.orchestrate",
            ]))
            ->values();
    }
}
