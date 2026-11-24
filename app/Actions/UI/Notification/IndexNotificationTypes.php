<?php

/*
 * Author Louis Perez
 * Created on 24-11-2026-16h-30m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\UI\Notification;

use App\Models\SysAdmin\User;
use App\Notifications\WorkNotification;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class IndexNotificationTypes
{
    use AsAction;

    private const array GROUPS = ['work', 'stock', 'purchasing', 'production', 'shopping_assistant', 'money_requests', 'other', 'older'];

    /**
     * @return list<array{value: string, label: string, group: string, group_label: string, count: int}>
     */
    public function handle(User $user): array
    {
        return $user->notifications()
            ->reorder()
            ->whereNot('type', WorkNotification::class)
            ->selectRaw('type, count(*) as notifications_count')
            ->groupBy('type')
            ->toBase()
            ->get()
            ->map(fn (object $row) => $this->describe($row->type, (int) $row->notifications_count))
            ->sortBy([
                fn (array $a, array $b) => array_search($a['group'], self::GROUPS) <=> array_search($b['group'], self::GROUPS),
                fn (array $a, array $b) => strcasecmp($a['label'], $b['label']),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{value: string, label: string, group: string, group_label: string, count: int}
     */
    public function describe(string $type, int $count = 0): array
    {
        $className = class_basename($type);
        [$group, $label] = $this->knownTypes()[$className] ?? ['other', $this->labelFromClassName($className)];

        return [
            'value'       => $type,
            'label'       => $label,
            'group'       => $group,
            'group_label' => $this->groupLabels()[$group],
            'count'       => $count,
        ];
    }

    private function labelFromClassName(string $className): string
    {
        $words = Str::headline(Str::beforeLast($className, 'Notification') ?: $className);

        return Str::ucfirst(Str::lower($words));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    private function knownTypes(): array
    {
        return [
            'StaffTaskNotification'                           => ['work', __('Tasks')],
            'TicketNotification'                              => ['work', __('Tickets')],
            'TicketProjectNotification'                       => ['work', __('Ticket projects')],
            'ProductDevelopmentNotification'                  => ['work', __('Product development')],
            'LeaveCoverNotification'                          => ['work', __('Leave cover')],
            'ShopStockArrivalsNotification'                   => ['stock', __('Back in stock')],
            'OrgStockPackingChangedNotification'              => ['stock', __('SKO packing changed')],
            'OrgStocksBelowMinimumStockNotification'          => ['stock', __('SKOs below minimum stock')],
            'PurchaseOrderBalanceDueNotification'             => ['purchasing', __('PO balance due')],
            'PurchaseOrderStageChangedByAgentNotification'    => ['purchasing', __('PO stage changed by agent')],
            'PurchaseOrderPriceChangeAnsweredNotification'    => ['purchasing', __('PO price change answered')],
            'PurchaseOrderQuantityChangeAnsweredNotification' => ['purchasing', __('PO quantity change answered')],
            'PurchaseOrderMinimumPokeNotification'            => ['purchasing', __('PO minimum reminder')],
            'StuckPurchaseOrdersSummaryNotification'          => ['purchasing', __('Stuck purchase orders')],
            'StockDeliveryBookedInWithoutCostsNotification'   => ['purchasing', __('Delivery booked in without costs')],
            'StockDeliveryReceivedWithoutCostsNotification'   => ['purchasing', __('Delivery received without costs')],
            'StockDeliveryFeedbackNotification'               => ['purchasing', __('Stock delivery feedback')],
            'SupplierProductAvailabilityChangedNotification'  => ['purchasing', __('Supplier product availability')],
            'SupplierDepositRequestedNotification'            => ['purchasing', __('Supplier deposit requested')],
            'PartnerLinePokedNotification'                    => ['purchasing', __('Partner line poked')],
            'ProductionShortfallNotification'                 => ['production', __('Production shortfall')],
            'ToProduceItemsCantBeDoneNotification'            => ['production', __('Items that can’t be produced')],
            'ManufacturePayrollChangedNotification'           => ['production', __('Payroll changed')],
            'ShoppingAssistantTeamQuestionNotification'       => ['shopping_assistant', __('Team questions')],
            'ShoppingAssistantRestockQuestionNotification'    => ['shopping_assistant', __('Restock questions')],
            'ShoppingAssistantMorningRoundNotification'       => ['shopping_assistant', __('Morning round')],
            'ShoppingAssistantGuruRefusedNotification'        => ['shopping_assistant', __('Guru refused')],
            'MoneyRequestCreatedNotification'                 => ['money_requests', __('Money request created')],
            'MoneyRequestApprovedNotification'                => ['money_requests', __('Money request approved')],
            'MoneyRequestPaidNotification'                    => ['money_requests', __('Money request paid')],
            'MeasurementShareNotification'                    => ['older', __('Measurement shares')],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function groupLabels(): array
    {
        return [
            'work'               => __('Work'),
            'stock'              => __('Stock'),
            'purchasing'         => __('Purchasing'),
            'production'         => __('Production'),
            'shopping_assistant' => __('Shopping assistant'),
            'money_requests'     => __('Money requests'),
            'other'              => __('Other'),
            'older'              => __('Older'),
        ];
    }

    /**
     * @return list<array{value: string, label: string, group: string, group_label: string, count: int}>
     */
    public function asController(ActionRequest $request): array
    {
        return $this->handle($request->user());
    }
}
