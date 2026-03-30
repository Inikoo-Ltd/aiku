<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 28 Mar 2025 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer\UI;

use App\Enums\Comms\DispatchedEmail\DispatchedEmailStateEnum;
use App\Enums\Helpers\Audit\AuditEventEnum;
use App\Models\Accounting\Payment;
use App\Models\Analytics\WebUserRequest;
use App\Models\CRM\Customer;
use App\Models\Helpers\History;
use App\Models\Ordering\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsObject;

class GetCustomerTimeline
{
    use AsObject;

    public function handle(Customer $customer): array
    {
        $cutoff = now()->subMonths(12);
        $limit  = 50;

        $events = collect();

        $this->appendHistoryEvents($customer, $cutoff, $limit, $events);
        $this->appendOrderEvents($customer, $cutoff, $limit, $events);
        $this->appendPaymentEvents($customer, $cutoff, $limit, $events);
        $this->appendEmailEvents($customer, $cutoff, $limit, $events);
        $this->appendWebUserLoginEvents($customer, $cutoff, $events);

        return [
            'events' => $events->sortByDesc('timestamp')->values()->map(function (array $event) {
                unset($event['timestamp']);
                return $event;
            })->all(),
        ];
    }

    private function appendHistoryEvents(Customer $customer, Carbon $cutoff, int $limit, Collection $events): void
    {
        History::where('customer_id', $customer->id)
            ->where('created_at', '>=', $cutoff)
            ->latest()
            ->limit($limit)
            ->get()
            ->each(function (History $history) use ($events) {
                $isNote = $history->event === AuditEventEnum::CUSTOMER_NOTE->value;

                $events->push([
                    'id'        => "audit_{$history->id}",
                    'type'      => $isNote ? 'note' : 'account_update',
                    'timestamp' => $history->created_at,
                    'datetime'  => $history->created_at?->toIso8601String(),
                    'title'     => $this->getHistoryTitle($history),
                    'subtitle'  => $history->comments,
                    'icon'      => $isNote ? ['fal', 'fa-sticky-note'] : ['fal', 'fa-user-edit'],
                    'color'     => $isNote ? 'yellow' : 'blue',
                    'metadata'  => [
                        'event'      => $history->event,
                        'old_values' => $history->old_values,
                        'new_values' => $history->new_values,
                    ],
                ]);
            });
    }

    private function getHistoryTitle(History $history): string
    {
        return match ($history->event) {
            AuditEventEnum::CREATED->value       => __('Account created'),
            AuditEventEnum::UPDATED->value       => $this->getUpdateTitle($history),
            AuditEventEnum::DELETED->value       => __('Account deleted'),
            AuditEventEnum::RESTORED->value      => __('Account restored'),
            AuditEventEnum::CUSTOMER_NOTE->value => __('Note added'),
            default                              => ucfirst(str_replace('_', ' ', $history->event)),
        };
    }

    private function getUpdateTitle(History $history): string
    {
        $fields = array_keys($history->new_values ?? []);

        if (empty($fields)) {
            return __('Account updated');
        }

        return match ($fields[0]) {
            'email'        => __('Email updated'),
            'phone'        => __('Phone updated'),
            'name'         => __('Name updated'),
            'company_name' => __('Company name updated'),
            'status'       => __('Account status updated'),
            'state'        => __('Account state updated'),
            default        => __('Account updated'),
        };
    }

    private function appendOrderEvents(Customer $customer, Carbon $cutoff, int $limit, Collection $events): void
    {
        $customer->orders()
            ->with(['currency:id,code'])
            ->where(function ($q) use ($cutoff) {
                $q->where('submitted_at', '>=', $cutoff)
                    ->orWhere('dispatched_at', '>=', $cutoff)
                    ->orWhere('cancelled_at', '>=', $cutoff);
            })
            ->latest('submitted_at')
            ->limit($limit)
            ->get()
            ->each(function (Order $order) use ($events) {
                $currencyCode = $order->currency?->code ?? '';

                if ($order->submitted_at) {
                    $events->push([
                        'id'        => "order_placed_{$order->id}",
                        'type'      => 'order_placed',
                        'timestamp' => $order->submitted_at,
                        'datetime'  => $order->submitted_at->toIso8601String(),
                        'title'     => __('Order placed'),
                        'subtitle'  => '#'.$order->reference,
                        'icon'      => ['fal', 'fa-inbox-in'],
                        'color'     => 'indigo',
                        'metadata'  => [
                            'reference'     => $order->reference,
                            'slug'          => $order->slug,
                            'state'         => $order->state->value,
                            'net_amount'    => $order->net_amount,
                            'total_amount'  => $order->total_amount,
                            'currency_code' => $currencyCode,
                        ],
                    ]);
                }

                if ($order->dispatched_at) {
                    $events->push([
                        'id'        => "order_dispatched_{$order->id}",
                        'type'      => 'order_dispatched',
                        'timestamp' => $order->dispatched_at,
                        'datetime'  => $order->dispatched_at->toIso8601String(),
                        'title'     => __('Order dispatched'),
                        'subtitle'  => '#'.$order->reference,
                        'icon'      => ['fal', 'fa-paper-plane'],
                        'color'     => 'green',
                        'metadata'  => [
                            'reference'     => $order->reference,
                            'slug'          => $order->slug,
                            'currency_code' => $currencyCode,
                        ],
                    ]);
                }

                if ($order->cancelled_at) {
                    $events->push([
                        'id'        => "order_cancelled_{$order->id}",
                        'type'      => 'order_cancelled',
                        'timestamp' => $order->cancelled_at,
                        'datetime'  => $order->cancelled_at->toIso8601String(),
                        'title'     => __('Order cancelled'),
                        'subtitle'  => '#'.$order->reference,
                        'icon'      => ['fal', 'fa-times-circle'],
                        'color'     => 'red',
                        'metadata'  => [
                            'reference'     => $order->reference,
                            'slug'          => $order->slug,
                            'currency_code' => $currencyCode,
                        ],
                    ]);
                }
            });
    }

    private function appendPaymentEvents(Customer $customer, Carbon $cutoff, int $limit, Collection $events): void
    {
        $customer->payments()
            ->where('created_at', '>=', $cutoff)
            ->latest()
            ->limit($limit)
            ->get()
            ->each(function (Payment $payment) use ($events) {
                $timestamp = $payment->completed_at
                    ? Carbon::parse($payment->completed_at)
                    : $payment->created_at;

                $events->push([
                    'id'        => "payment_{$payment->id}",
                    'type'      => 'payment',
                    'timestamp' => $timestamp,
                    'datetime'  => $timestamp?->toIso8601String(),
                    'title'     => __('Payment received'),
                    'subtitle'  => $payment->reference,
                    'icon'      => ['fal', 'fa-money-bill'],
                    'color'     => 'green',
                    'metadata'  => [
                        'reference' => $payment->reference,
                        'amount'    => $payment->amount,
                        'state'     => $payment->state->value,
                        'status'    => $payment->status->value,
                    ],
                ]);
            });
    }

    private function appendWebUserLoginEvents(Customer $customer, Carbon $cutoff, Collection $events): void
    {
        $customer->webUsers()->get()->each(function ($webUser) use ($cutoff, $events) {
            WebUserRequest::where('web_user_id', $webUser->id)
                ->where('date', '>=', $cutoff->toDateString())
                ->selectRaw('date, os, device, browser, location, MIN(id) as id')
                ->groupBy('date', 'os', 'device', 'browser', 'location')
                ->orderByDesc('date')
                ->limit(20)
                ->get()
                ->each(function (WebUserRequest $request) use ($webUser, $events) {
                    $timestamp = Carbon::parse($request->date);

                    $events->push([
                        'id'        => "web_login_{$webUser->id}_{$request->id}",
                        'type'      => 'web_login',
                        'timestamp' => $timestamp,
                        'datetime'  => $timestamp->toIso8601String(),
                        'title'     => __('Website visit'),
                        'subtitle'  => $request->browser && $request->device ? "{$request->browser} · {$request->device}" : null,
                        'icon'      => ['fal', 'fa-globe'],
                        'color'     => 'teal',
                        'metadata'  => [
                            'browser'  => $request->browser,
                            'device'   => $request->device,
                            'os'       => $request->os,
                            'location' => $request->location,
                        ],
                    ]);
                });
        });
    }

    private function appendEmailEvents(Customer $customer, Carbon $cutoff, int $limit, Collection $events): void
    {
        $customer->dispatchedEmails()
            ->with(['mailshot:id,subject', 'outbox:id,name'])
            ->where(function ($q) use ($cutoff) {
                $q->where('dispatched_emails.sent_at', '>=', $cutoff)
                    ->orWhere('dispatched_emails.created_at', '>=', $cutoff);
            })
            ->latest('dispatched_emails.sent_at')
            ->limit($limit)
            ->get()
            ->each(function ($email) use ($events) {
                $timestamp   = $email->sent_at ?? $email->created_at;
                $stateLabel  = DispatchedEmailStateEnum::labels()[$email->state->value] ?? $email->state->value;
                $campaignName = $email->mailshot?->subject ?? $email->outbox?->name ?? null;

                $events->push([
                    'id'        => "email_{$email->id}",
                    'type'      => 'email',
                    'timestamp' => $timestamp,
                    'datetime'  => $timestamp?->toIso8601String(),
                    'title'     => $campaignName ? __('Email: :name', ['name' => $campaignName]) : __('Email sent'),
                    'subtitle'  => $stateLabel,
                    'icon'      => ['fal', 'fa-envelope'],
                    'color'     => 'purple',
                    'metadata'  => [
                        'state'         => $email->state->value,
                        'number_reads'  => $email->number_reads,
                        'number_clicks' => $email->number_clicks,
                    ],
                ]);
            });
    }
}
