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
            ->leftJoin('org_suppliers', fn ($join) => $join->on('org_suppliers.supplier_id', 'purchase_orders.supplier_id')->on('org_suppliers.organisation_id', 'purchase_orders.organisation_id'))
            ->leftJoin('users as buyers', 'buyers.id', 'purchase_orders.buyer_id')
            ->select([
                'purchase_orders.id',
                'purchase_orders.slug',
                'purchase_orders.reference',
                'purchase_orders.submitted_at',
                'suppliers.slug as supplier_slug',
                'suppliers.code as supplier_code',
                'suppliers.name as supplier_name',
                'suppliers.company_name as supplier_company_name',
                'org_suppliers.slug as org_supplier_slug',
                'buyers.contact_name as buyer_name',
                'buyers.username as buyer_username',
            ])]);
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
                    $depositRequest->items->map(fn (DepositRequestItem $item) => self::describeLine($depositRequest, $item, $role === MoneyRequestCreatedNotification::FOR_REQUESTER))->values()->all(),
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

    /**
     * The agent user who asked opens the agent's copy of the order and supplier; the client's users open their own.
     *
     * @return array{description: string, amount: string, purchase_order: array{reference: string, url: string, submitted_at: string|null, placed_by: string|null, supplier_code: string|null, supplier_company: string|null, supplier_nickname: string|null, supplier_url: string|null}|null}
     */
    private static function describeLine(DepositRequest $depositRequest, DepositRequestItem $item, bool $forAgent): array
    {
        $purchaseOrder = $item->purchaseOrder;
        $line          = [
            'description'    => $item->kind->describeForPurchaseOrder(),
            'amount'         => self::money($depositRequest, (float) $item->amount),
            'purchase_order' => null,
        ];

        if (!$purchaseOrder) {
            return $line;
        }

        $agentOrganisationSlug = $depositRequest->agent->organisation->slug;
        $clientSlug            = $depositRequest->organisation->slug;
        $company               = $purchaseOrder->supplier_company_name ?: $purchaseOrder->supplier_name;

        $supplierUrl = match (true) {
            $forAgent && (bool) $purchaseOrder->supplier_slug      => route('grp.org.agent.sales.suppliers.show', [$agentOrganisationSlug, $purchaseOrder->supplier_slug]),
            !$forAgent && (bool) $purchaseOrder->org_supplier_slug => route('grp.org.procurement.org_suppliers.show', [$clientSlug, $purchaseOrder->org_supplier_slug]),
            default                                                => null,
        };

        $line['purchase_order'] = [
            'reference'         => $purchaseOrder->reference,
            'url'               => $forAgent
                ? route('grp.org.agent.purchase_orders.show', [$agentOrganisationSlug, $purchaseOrder->slug])
                : route('grp.org.procurement.purchase_orders.show', [$clientSlug, $purchaseOrder->slug]),
            'submitted_at'      => $purchaseOrder->submitted_at?->isoFormat('D MMM YYYY'),
            'placed_by'         => $purchaseOrder->buyer_name ?: $purchaseOrder->buyer_username,
            'supplier_code'     => $purchaseOrder->supplier_code,
            'supplier_company'  => $company,
            'supplier_nickname' => $purchaseOrder->supplier_name && $purchaseOrder->supplier_name !== $company ? $purchaseOrder->supplier_name : null,
            'supplier_url'      => $supplierUrl,
        ];

        return $line;
    }

    public static function money(DepositRequest $depositRequest, float $amount): string
    {
        return $depositRequest->currency->code.' '.number_format($amount, 2);
    }
}
