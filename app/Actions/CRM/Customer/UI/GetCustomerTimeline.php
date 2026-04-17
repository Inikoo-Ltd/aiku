<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 28 Mar 2025 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer\UI;

use App\Enums\Comms\DispatchedEmail\DispatchedEmailStateEnum;
use App\Enums\CRM\Customer\CustomerWebActivityTypeEnum;
use App\Enums\GoodsIn\Return\ReturnStateEnum;
use App\Enums\Helpers\Audit\AuditEventEnum;
use App\Models\Accounting\Payment;
use App\Models\CRM\Customer;
use App\Models\CRM\CustomerWebActivity;
use App\Models\GoodsIn\OrderReturn;
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
        $this->appendReturnEvents($customer, $cutoff, $limit, $events);
        $this->appendWebActivityEvents($customer, $cutoff, $events);

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
            ->where('auditable_type', 'Customer')
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

    private function appendReturnEvents(Customer $customer, Carbon $cutoff, int $limit, Collection $events): void
    {
        OrderReturn::where('customer_id', $customer->id)
            ->where('date', '>=', $cutoff)
            ->latest('date')
            ->limit($limit)
            ->get()
            ->each(function (OrderReturn $return) use ($events) {
                $stateLabels = ReturnStateEnum::labels();
                $stateLabel  = $stateLabels[$return->state->value] ?? $return->state->value;

                $events->push([
                    'id'        => "return_{$return->id}",
                    'type'      => 'return',
                    'timestamp' => $return->date,
                    'datetime'  => $return->date->toIso8601String(),
                    'title'     => __('Return request'),
                    'subtitle'  => '#'.$return->reference.' · '.$stateLabel,
                    'icon'      => ['fal', 'fa-undo'],
                    'color'     => 'orange',
                    'metadata'  => [
                        'reference'     => $return->reference,
                        'state'         => $return->state->value,
                        'number_items'  => $return->number_items,
                        'return_reason' => $return->return_reason,
                    ],
                ]);
            });
    }

    private function appendWebActivityEvents(Customer $customer, Carbon $cutoff, Collection $events): void
    {
        CustomerWebActivity::where('customer_id', $customer->id)
            ->where('activity_date', '>=', $cutoff->toDateString())
            ->orderByDesc('activity_date')
            ->limit(100)
            ->get()
            ->each(function (CustomerWebActivity $activity) use ($events) {
                $timestamp = Carbon::parse($activity->activity_date);

                match ($activity->activity_type) {
                    CustomerWebActivityTypeEnum::ProductView => $events->push([
                        'id'        => "web_product_view_{$activity->id}",
                        'type'      => 'product_view',
                        'timestamp' => $timestamp,
                        'datetime'  => $timestamp->toIso8601String(),
                        'title'     => __('Product viewed'),
                        'subtitle'  => $activity->page_path,
                        'icon'      => ['fal', 'fa-eye'],
                        'color'     => 'teal',
                        'metadata'  => [
                            'page_path'        => $activity->page_path,
                            'product_id'       => $activity->product_id,
                            'duration_seconds' => $activity->duration_seconds,
                        ],
                    ]),
                    CustomerWebActivityTypeEnum::AddToBasket => $events->push([
                        'id'        => "web_add_to_basket_{$activity->id}",
                        'type'      => 'add_to_basket',
                        'timestamp' => $timestamp,
                        'datetime'  => $timestamp->toIso8601String(),
                        'title'     => __('Added to basket'),
                        'subtitle'  => $activity->page_path,
                        'icon'      => ['fal', 'fa-shopping-cart'],
                        'color'     => 'green',
                        'metadata'  => [
                            'page_path'  => $activity->page_path,
                            'product_id' => $activity->product_id,
                            'quantity'   => $activity->quantity,
                        ],
                    ]),
                    CustomerWebActivityTypeEnum::PageView => $events->push([
                        'id'        => "web_page_view_{$activity->id}",
                        'type'      => 'page_view',
                        'timestamp' => $timestamp,
                        'datetime'  => $timestamp->toIso8601String(),
                        'title'     => __('Page visited'),
                        'subtitle'  => $activity->page_path,
                        'icon'      => ['fal', 'fa-globe'],
                        'color'     => 'teal',
                        'metadata'  => [
                            'page_path'        => $activity->page_path,
                            'page_type'        => $activity->page_type,
                            'page_sub_type'    => $activity->page_sub_type,
                            'duration_seconds' => $activity->duration_seconds,
                        ],
                    ]),
                };
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
