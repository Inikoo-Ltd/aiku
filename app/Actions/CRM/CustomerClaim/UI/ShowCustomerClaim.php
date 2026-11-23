<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\CustomerClaim\UI;

use App\Actions\Chat\ChatSession\PlanChatClaim;
use App\Actions\CRM\CustomerClaim\RejectCustomerClaim;
use App\Actions\Helpers\Images\GetPictureSources;
use App\Actions\OrgAction;
use App\Enums\CRM\CustomerClaim\CustomerClaimKindEnum;
use App\Enums\CRM\CustomerClaim\CustomerClaimSourceEnum;
use App\Enums\CRM\CustomerClaim\CustomerClaimStateEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Ordering\Transaction\UpcomingTransactionStateEnum;
use App\Enums\Tasks\StaffTaskStatusEnum;
use App\Enums\SysAdmin\Authorisation\ShopPermissionsEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\CustomerClaim\CustomerClaim;
use App\Models\CRM\CustomerClaim\CustomerClaimLine;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Helpers\Media;
use App\Models\Ordering\UpcomingTransaction;
use App\Models\Tasks\StaffTask;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * One customer claim for customer services to review (HELP-4869): what was claimed and why, the
 * photos per line and of the box, the customer's comment and earlier claims, and Approve or Reject.
 * Approve settles with the Claim panel's actions, so it is offered only to who may settle that outcome.
 */
class ShowCustomerClaim extends OrgAction
{
    use WithCustomerClaimsSection;

    private CustomerClaim $claim;

    public static function resolutionLabel(string $resolution): string
    {
        return match ($resolution) {
            'refund_to_balance' => __('Credit to balance'),
            'replacement'       => __('Replacement now'),
            'next_order'        => __('Free in next order'),
            'invoice_refund'    => __('Invoice refund'),
            default             => $resolution,
        };
    }

    public static function canSettle(CustomerClaim $claim, $user): bool
    {
        return $user->authTo(ShopPermissionsEnum::getPermissionName(PlanChatClaim::permission($claim->resolution)->value, $claim->shop));
    }

    public function htmlResponse(CustomerClaim $claim, ActionRequest $request): Response
    {
        $claim->loadMissing(['lines', 'customer', 'order', 'conversation', 'reviewer', 'media', 'refund']);
        $photos    = $claim->getMedia(CustomerClaim::PHOTOS);
        $photoUrls = fn (string $scope) => $photos->filter(fn (Media $media) => $media->media_scope === $scope)
            ->map(fn (Media $media) => GetPictureSources::run($media->getImage()->resize(0, 0))['original'])->values()->all();
        $earlier   = CustomerClaim::where('customer_id', $claim->customer_id)->where('id', '!=', $claim->id)->latest('id')->limit(10)->get();
        $params    = $request->route()->originalParameters();

        return Inertia::render('Org/Shop/CRM/CustomerClaim', [
            'breadcrumbs' => $this->claimsBreadcrumbs($claim->shop, ['route' => ['name' => 'grp.org.shops.show.claims.show', 'parameters' => $params], 'label' => $claim->reference]),
            'title'       => $claim->reference,
            'pageHead'    => [
                'title' => $claim->reference,
                'model' => __('Claim'),
                'icon'  => ['icon' => ['fal', 'fa-box-fragile'], 'title' => __('Claim')],
            ],
            'claim'       => [
                'ulid'             => $claim->ulid,
                'reference'        => $claim->reference,
                'state'            => $claim->state->value,
                'state_label'      => $claim->state->label(),
                'source'           => $claim->source->value,
                'resolution'       => $claim->resolution,
                'resolution_label' => self::resolutionLabel($claim->resolution),
                'amount'           => (float) $claim->amount,
                'currency_code'    => $claim->currency_code,
                'comment'          => $claim->comment,
                'created_at'       => $claim->created_at,
                'reviewed_at'      => $claim->reviewed_at,
                'reviewer'         => $claim->reviewer?->contact_name ?? $claim->reviewer?->username,
                'settled_by'       => $claim->source === CustomerClaimSourceEnum::STAFF || $claim->reviewed_by_user_id ? $this->userName($claim) : __('Approved automatically'),
                'refund'           => $claim->refund ? ['reference' => $claim->refund->reference, 'in_process' => (bool) $claim->refund->in_process, 'route' => $claim->order ? ['name' => 'grp.org.shops.show.ordering.orders.show.refunds.show', 'parameters' => [$params['organisation'], $params['shop'], $claim->order->slug, $claim->refund->slug]] : null] : null,
                'rejection_reason' => $claim->rejection_reason,
                'rejection_note'   => $claim->rejection_note,
                'result'           => $claim->result,
                'box_photos'       => $photoUrls('box'),
                'lines'            => $claim->lines->map(fn (CustomerClaimLine $line) => [
                    'code'     => $line->code,
                    'name'     => $line->name,
                    'quantity' => (float) $line->quantity,
                    'reason'   => $line->reason->label(),
                    'photos'   => $photoUrls('transaction:'.$line->transaction_id),
                ])->all(),
                'customer'         => ['name' => $claim->customer->name, 'reference' => $claim->customer->reference, 'route' => ['name' => 'grp.org.shops.show.crm.customers.show', 'parameters' => [$params['organisation'], $params['shop'], $claim->customer->slug]]],
                'order'            => $claim->order ? ['reference' => $claim->order->reference, 'route' => ['name' => 'grp.org.shops.show.ordering.orders.show', 'parameters' => [$params['organisation'], $params['shop'], $claim->order->slug]]] : null,
                'kind'             => $claim->kind->value,
                'kind_label'       => $claim->kind->label(),
                'conversation'     => $claim->conversation ? ['route' => ['name' => 'grp.org.shops.show.ai_salesman.conversations.show', 'parameters' => [$params['organisation'], $params['shop'], $claim->conversation->ulid]]] : null,
                'timeline'         => $this->timeline($claim, $params),
                'claims_of_customer' => CustomerClaim::where('customer_id', $claim->customer_id)->count(),
                'earlier'          => $earlier->map(fn (CustomerClaim $other) => [
                    'reference'   => $other->reference,
                    'state_label' => $other->state->label(),
                    'resolution'  => self::resolutionLabel($other->resolution),
                    'created_at'  => $other->created_at,
                    'route'       => ['name' => 'grp.org.shops.show.claims.show', 'parameters' => [$params['organisation'], $params['shop'], $other->ulid]],
                ])->all(),
            ],
            'can_review'        => $claim->state === CustomerClaimStateEnum::IN_REVIEW && self::canSettle($claim, $request->user()),
            'can_approve'       => $claim->resolution !== 'invoice_refund',
            'claims_route'      => ['name' => 'grp.org.shops.show.claims.pending', 'parameters' => [$params['organisation'], $params['shop']]],
            'approve_route'     => ['name' => 'grp.models.customer_claim.approve', 'parameters' => ['customerClaim' => $claim->ulid]],
            'reject_route'      => ['name' => 'grp.models.customer_claim.reject', 'parameters' => ['customerClaim' => $claim->ulid]],
            'rejection_reasons' => collect(RejectCustomerClaim::REASONS)->map(fn (string $reason) => ['value' => $reason, 'label' => RejectCustomerClaim::reasonLabel($reason)])->all(),
        ]);
    }

    /**
     * What happened to the claim, oldest first: made, sent to customer services, approved (by whom,
     * or automatically) or rejected, and then what the settlement became: the refund, the replacement
     * delivery note and where it is now, the free items and the order they went into.
     *
     * @return array<int, array{at: mixed, icon: string, tone: string, label: string, detail: ?string, route: ?array}>
     */
    private function timeline(CustomerClaim $claim, array $params): array
    {
        $events = [[
            'at'     => $claim->created_at,
            'icon'   => $claim->source === CustomerClaimSourceEnum::MERLIN ? 'fal fa-hat-wizard' : 'fal fa-user',
            'tone'   => 'gray',
            'label'  => $claim->source === CustomerClaimSourceEnum::STAFF
                ? __(':kind recorded by :user', ['kind' => $claim->kind->label(), 'user' => $this->userName($claim)])
                : __('Claim made by :customer', ['customer' => $claim->customer->name]),
            'detail' => match ($claim->source) {
                CustomerClaimSourceEnum::MERLIN  => __('In Merlin'),
                CustomerClaimSourceEnum::ACCOUNT => __('From their account'),
                CustomerClaimSourceEnum::CHAT    => __('In the website chat'),
                CustomerClaimSourceEnum::EMAIL   => __('From an email link'),
                CustomerClaimSourceEnum::STAFF   => __('By staff'),
            },
            'route'  => null,
        ]];

        foreach (StaffTask::where('model_type', 'CustomerClaim')->where('model_id', $claim->id)->where('created_at', '>=', $claim->created_at)->orderBy('id')->get() as $task) {
            $events[] = ['at' => $task->created_at, 'icon' => 'fal fa-tasks', 'tone' => 'gray', 'label' => __('Sent to customer services as :task', ['task' => $task->reference]), 'detail' => StaffTaskStatusEnum::labels()[$task->status->value] ?? $task->status->value, 'route' => ['name' => 'grp.tasks.show', 'parameters' => [$task->reference]]];
        }

        if ($claim->state === CustomerClaimStateEnum::REJECTED) {
            $events[] = ['at' => $claim->reviewed_at, 'icon' => 'fal fa-ban', 'tone' => 'red', 'label' => __('Rejected by :user', ['user' => $this->userName($claim)]), 'detail' => trim(RejectCustomerClaim::reasonLabel((string) $claim->rejection_reason).($claim->rejection_note ? ': '.$claim->rejection_note : '')), 'route' => null];
        }

        if ($claim->state === CustomerClaimStateEnum::SETTLED) {
            $events[] = $claim->source === CustomerClaimSourceEnum::STAFF
                ? ['at' => $claim->settled_at, 'icon' => 'fal fa-check', 'tone' => 'green', 'label' => __('Settled by :user', ['user' => $this->userName($claim)]), 'detail' => self::resolutionLabel($claim->resolution), 'route' => null]
                : ($claim->reviewed_by_user_id
                ? ['at' => $claim->reviewed_at, 'icon' => 'fal fa-check', 'tone' => 'green', 'label' => __('Approved by :user', ['user' => $this->userName($claim)]), 'detail' => self::resolutionLabel($claim->resolution), 'route' => null]
                : ['at' => $claim->settled_at, 'icon' => 'fal fa-bolt', 'tone' => 'green', 'label' => __('Approved automatically'), 'detail' => __('Within the customer\'s allowance'), 'route' => null]);

            $events = array_merge($events, $this->outcomeEvents($claim, $params));
        }

        return collect($events)->filter(fn (array $event) => $event['at'] !== null)->sortBy(fn (array $event) => $event['at'])->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function outcomeEvents(CustomerClaim $claim, array $params): array
    {
        $result = $claim->result ?? [];
        $events = [];

        if ($reference = $result['refund'] ?? null) {
            $events[] = ['at' => $claim->settled_at, 'icon' => 'fal fa-hand-holding-usd', 'tone' => 'green', 'label' => $claim->kind === CustomerClaimKindEnum::ITEMS && $claim->resolution === 'refund_to_balance' ? __('Refund :reference credited to the balance', ['reference' => $reference]) : __('Refund :reference finalised', ['reference' => $reference]), 'detail' => null, 'route' => null];
        }

        if ($reference = $result['replacement'] ?? null) {
            $note = DeliveryNote::where('shop_id', $claim->shop_id)->where('reference', $reference)->first();
            $events[] = ['at' => $claim->settled_at, 'icon' => 'fal fa-truck', 'tone' => 'green', 'label' => __('Replacement :reference sent to the warehouse', ['reference' => $reference]), 'detail' => null, 'route' => $note && $claim->order ? ['name' => 'grp.org.shops.show.ordering.orders.show.delivery-note', 'parameters' => [$params['organisation'], $params['shop'], $claim->order->slug, $note->slug]] : null];
            if ($note?->dispatched_at) {
                $events[] = ['at' => $note->dispatched_at, 'icon' => 'fal fa-shipping-fast', 'tone' => 'green', 'label' => __('Replacement :reference dispatched', ['reference' => $reference]), 'detail' => null, 'route' => null];
            } elseif ($note) {
                $events[] = ['at' => now(), 'icon' => 'fal fa-warehouse', 'tone' => 'amber', 'label' => __('Replacement :reference now', ['reference' => $reference]), 'detail' => DeliveryNoteStateEnum::labels()[$note->state->value] ?? $note->state->value, 'route' => null];
            }
        }

        if (!empty($result['in_next_order'])) {
            $events[] = ['at' => $claim->settled_at, 'icon' => 'fal fa-gift', 'tone' => 'green', 'label' => __('Added free to the next order'), 'detail' => implode(', ', $result['in_next_order']), 'route' => null];

            $followOns = UpcomingTransaction::where('customer_id', $claim->customer_id)
                ->whereIn('source_transaction_id', $claim->lines->pluck('transaction_id'))
                ->where('created_at', '>=', $claim->settled_at->copy()->subMinute())
                ->with('order:id,reference,slug')
                ->get();
            foreach ($followOns->where('state', UpcomingTransactionStateEnum::APPLIED)->groupBy('order_id') as $applied) {
                $order = $applied->first()->order;
                if ($order) {
                    $events[] = ['at' => $applied->max('updated_at'), 'icon' => 'fal fa-shopping-cart', 'tone' => 'green', 'label' => __('Put in order :reference', ['reference' => $order->reference]), 'detail' => null, 'route' => ['name' => 'grp.org.shops.show.ordering.orders.show', 'parameters' => [$params['organisation'], $params['shop'], $order->slug]]];
                }
            }
            if ($followOns->isNotEmpty() && $followOns->every(fn ($followOn) => $followOn->state === UpcomingTransactionStateEnum::READY)) {
                $events[] = ['at' => now(), 'icon' => 'fal fa-hourglass-half', 'tone' => 'amber', 'label' => __('Waiting for the customer\'s next order'), 'detail' => null, 'route' => null];
            }
        }

        return $events;
    }

    private function userName(CustomerClaim $claim): string
    {
        return $claim->reviewer?->contact_name ?: ($claim->reviewer?->username ?? __('a person'));
    }

    public function asController(Organisation $organisation, Shop $shop, CustomerClaim $customerClaim, ActionRequest $request): CustomerClaim
    {
        abort_unless($customerClaim->shop_id === $shop->id, 404);
        $this->initialisationFromShop($shop, $request);

        return $customerClaim;
    }
}
