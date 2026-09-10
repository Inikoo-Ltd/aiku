<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\WhatsappSubscriber;

use App\Enums\Comms\WhatsappSubscriber\WhatsappSubscriberOptInMethodEnum;
use App\Models\Comms\WhatsappSubscriber;
use App\Models\CRM\Customer;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Mirrors customer_comms.is_subscribed_to_whatsapp_newsletter into whatsapp_subscribers so the
 * table is the live source of truth for who is subscribed rather than an unwritten shell.
 *
 * Idempotent, so every write path that can touch the flag may call it unconditionally.
 */
class SyncCustomerWhatsappSubscriber
{
    use AsAction;

    public function handle(Customer $customer): ?WhatsappSubscriber
    {
        $isSubscribed = (bool)$customer->comms?->is_subscribed_to_whatsapp_newsletter;

        /* withTrashed because the table has no unique constraint: a resubscribe that ignored the
           soft deleted row would leave a duplicate behind on every opt out/opt in cycle. */
        $subscriber = WhatsappSubscriber::withTrashed()
            ->where('parent_type', 'Customer')
            ->where('parent_id', $customer->id)
            ->first();

        if (!$isSubscribed) {
            $subscriber?->delete();

            return null;
        }

        if ($subscriber) {
            $subscriber->restore();

            return $subscriber;
        }

        return WhatsappSubscriber::create([
            'group_id'        => $customer->group_id,
            'organisation_id' => $customer->organisation_id,
            'shop_id'         => $customer->shop_id,
            'parent_type'     => 'Customer',
            'parent_id'       => $customer->id,
            'opt_in_method'   => WhatsappSubscriberOptInMethodEnum::WEBSITE,
        ]);
    }

    public string $commandSignature = 'whatsapp_subscriber:sync {customer : Customer slug or id}';

    public function asCommand(Command $command): int
    {
        $argument = $command->argument('customer');

        $customer = is_numeric($argument)
            ? Customer::find($argument)
            : Customer::where('slug', $argument)->first();

        if (!$customer) {
            $command->error('Customer not found');

            return 1;
        }

        $subscriber = $this->handle($customer);

        $command->info($subscriber ? "Subscribed: $customer->slug" : "Unsubscribed: $customer->slug");

        return 0;
    }
}
