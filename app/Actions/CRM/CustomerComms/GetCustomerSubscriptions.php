<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Sep 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\CustomerComms;

use App\Enums\Comms\Outbox\OutboxTypeEnum;
use App\Models\CRM\Customer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * What a customer who says "I unsubscribed and you still email me" needs checked: what they
 * are subscribed to now, when they last unsubscribed, and which marketing emails reached their
 * address in the last 30 days. Unsubscribe dates are only kept from 25 Sep 2026; before that
 * the latest unsubscribe clicked in one of our emails stands in for them.
 */
class GetCustomerSubscriptions
{
    use AsAction;

    public const array MARKETING_CHANNELS = ['newsletter', 'marketing'];

    private const array MARKETING_OUTBOXES = [OutboxTypeEnum::NEWSLETTER, OutboxTypeEnum::MARKETING, OutboxTypeEnum::MARKETING_NOTIFICATION, OutboxTypeEnum::PUSH];

    /**
     * @return array{channels: array<string, array{subscribed: bool, unsubscribed_at: string|null}>, unsubscribed_in_an_email_at: string|null, marketing_emails_last_30_days: array<int, array{sent_at: string, outbox: string}>}
     */
    public function handle(Customer $customer): array
    {
        $comms = $customer->comms;

        $channels = collect($comms?->getAttributes() ?? [])
            ->filter(fn ($value, string $column) => str_starts_with($column, 'is_subscribed_to_'))
            ->mapWithKeys(function ($subscribed, string $column) use ($comms) {
                $channel = substr($column, strlen('is_subscribed_to_'));
                $at      = $comms->getAttribute($channel.'_unsubscribed_at');

                return [$channel => [
                    'subscribed'      => (bool) $subscribed,
                    'unsubscribed_at' => !$subscribed && $at ? Carbon::parse($at)->toDateString() : null,
                ]];
            })
            ->all();

        $emails = DB::table('dispatched_emails')
            ->join('email_addresses', 'email_addresses.id', '=', 'dispatched_emails.email_address_id')
            ->join('outboxes', 'outboxes.id', '=', 'dispatched_emails.outbox_id')
            ->where('email_addresses.email', mb_strtolower((string) $customer->email));

        $unsubscribedInAnEmail = (clone $emails)
            ->where(fn ($query) => $query->where('dispatched_emails.state', 'unsubscribed')->orWhere('dispatched_emails.provoked_unsubscribe', true))
            ->max('dispatched_emails.updated_at');

        $recent = (clone $emails)
            ->whereIn('outboxes.type', array_map(fn (OutboxTypeEnum $type) => $type->value, self::MARKETING_OUTBOXES))
            ->where('dispatched_emails.sent_at', '>=', now()->subDays(30))
            ->orderByDesc('dispatched_emails.sent_at')
            ->limit(10)
            ->get(['dispatched_emails.sent_at', 'outboxes.name as outbox'])
            ->map(fn ($email) => ['sent_at' => Carbon::parse($email->sent_at)->toDateString(), 'outbox' => (string) $email->outbox])
            ->all();

        return [
            'channels'                      => $channels,
            'unsubscribed_in_an_email_at'   => $unsubscribedInAnEmail ? Carbon::parse($unsubscribedInAnEmail)->toDateString() : null,
            'marketing_emails_last_30_days' => $recent,
        ];
    }
}
