<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-06 14:09
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\SupplyChain\DepositRequest;

use App\Actions\SysAdmin\User\NotifyUserOnChosenChannels;
use App\Enums\SysAdmin\User\UserNotificationEnum;
use App\Models\Procurement\OrgAgent;
use App\Models\SupplyChain\DepositRequest;
use App\Models\SupplyChain\DepositRequestItem;
use App\Notifications\MoneyRequestCreatedNotification;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * When an agent asks a client for money, the client's payers and copied users set on the client's agent page are
 * told where to approve and pay it, and the agent user who asked gets a copy.
 */
class NotifyMoneyRequestCreated
{
    use AsAction;
    use WithMoneyRequestRecipients;

    public function handle(DepositRequest $depositRequest, bool $isReminder = false): int
    {
        $depositRequest->loadMissing(['agent.organisation', 'organisation', 'currency', 'requester']);
        $depositRequest->load(['items.purchaseOrder' => fn ($query) => $query
            ->leftJoin('suppliers', 'suppliers.id', 'purchase_orders.supplier_id')
            ->select(['purchase_orders.id', 'purchase_orders.reference', 'suppliers.code as supplier_code'])]);
        $orgAgent   = self::clientOrgAgent($depositRequest);
        $recipients = $this->recipients(self::userIdsByRole($depositRequest, $orgAgent));

        $total         = (float) $depositRequest->items->sum('amount');
        $requesterName = $depositRequest->requester?->chatName() ?? $depositRequest->agent->code;

        foreach ($recipients as ['user' => $user, 'role' => $role]) {
            $route = $role === MoneyRequestCreatedNotification::FOR_REQUESTER || !$orgAgent
                ? ['name' => 'grp.org.agent.accounting.deposit_requests.index', 'parameters' => ['organisation' => $depositRequest->agent->organisation->slug]]
                : ['name' => 'grp.org.procurement.org_agents.show', 'parameters' => ['organisation' => $depositRequest->organisation->slug, 'orgAgent' => $orgAgent->slug, 'tab' => 'money']];

            $this->inLanguageOf($user, function () use ($depositRequest, $user, $role, $route, $total, $requesterName, $isReminder) {
                $notification = new MoneyRequestCreatedNotification(
                    $depositRequest,
                    $requesterName,
                    self::money($depositRequest, $total),
                    $depositRequest->items->map(fn (DepositRequestItem $item) => self::describeLine($depositRequest, $item))->values()->all(),
                    $role,
                    $route,
                    $isReminder
                );

                NotifyUserOnChosenChannels::run(
                    $user,
                    UserNotificationEnum::MONEY_REQUEST_CREATED,
                    $notification,
                    $notification->title(),
                    $notification->body(),
                    route($route['name'], $route['parameters'])
                );
            });
        }

        return $recipients->count();
    }

    /**
     * Who the email goes to (the client's payers) and who gets a copy (copied users and the agent user who asked).
     *
     * @return array{to: list<array{name: string, email: string|null, by_email: bool}>, cc: list<array{name: string, email: string|null, by_email: bool}>}
     */
    public static function emailRecipients(DepositRequest $depositRequest): array
    {
        $recipients = (new self())->recipients(self::userIdsByRole($depositRequest, self::clientOrgAgent($depositRequest)))
            ->map(fn (array $recipient) => [
                'role'     => $recipient['role'],
                'name'     => $recipient['user']->chatName(),
                'email'    => $recipient['user']->email,
                'by_email' => (bool) $recipient['user']->email && in_array('email', UserNotificationEnum::MONEY_REQUEST_CREATED->channelsFor($recipient['user']), true),
            ]);

        $withoutRole = fn ($recipients) => $recipients->map(fn (array $recipient) => Arr::except($recipient, 'role'))->values()->all();

        return [
            'to' => $withoutRole($recipients->where('role', MoneyRequestCreatedNotification::FOR_PAYER)),
            'cc' => $withoutRole($recipients->where('role', '!=', MoneyRequestCreatedNotification::FOR_PAYER)),
        ];
    }

    /**
     * @return array<string, array<int, int|null>>
     */
    private static function userIdsByRole(DepositRequest $depositRequest, ?OrgAgent $orgAgent): array
    {
        return [
            MoneyRequestCreatedNotification::FOR_PAYER     => $orgAgent?->moneyRequestPayerIds() ?? [],
            MoneyRequestCreatedNotification::FOR_COPY      => $orgAgent?->moneyRequestCcIds() ?? [],
            MoneyRequestCreatedNotification::FOR_REQUESTER => array_filter([$depositRequest->created_by]),
        ];
    }

    public static function clientOrgAgent(DepositRequest $depositRequest): ?OrgAgent
    {
        return OrgAgent::where('agent_id', $depositRequest->agent_id)
            ->where('organisation_id', $depositRequest->organisation_id)
            ->first();
    }

    private static function describeLine(DepositRequest $depositRequest, DepositRequestItem $item): string
    {
        $purchaseOrder = $item->purchaseOrder;
        $description   = $purchaseOrder
            ? trim($item->kind->describeForPurchaseOrder().' '.$purchaseOrder->reference.($purchaseOrder->supplier_code ? ' ('.$purchaseOrder->supplier_code.')' : ''))
            : $item->kind->describeForPurchaseOrder();

        return $description.': '.self::money($depositRequest, (float) $item->amount);
    }

    public static function money(DepositRequest $depositRequest, float $amount): string
    {
        return $depositRequest->currency->code.' '.number_format($amount, 2);
    }
}
