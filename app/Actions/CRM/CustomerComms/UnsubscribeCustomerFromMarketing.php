<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Sep 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\CustomerComms;

use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Actions\OrgAction;
use App\Models\CRM\Customer;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;

/**
 * A customer who asks us to stop emailing them is taken off everything optional in one go:
 * newsletters, marketing, reminders and WhatsApp newsletters. Emails about their own orders
 * are not subscriptions and keep coming. Customer service answering them may do it, as it is
 * the customer's own request.
 */
class UnsubscribeCustomerFromMarketing extends OrgAction
{
    use WithChatAgentAuthorisation;

    private Customer $customer;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("crm.{$this->customer->shop_id}.edit")
            || $this->userCanActOnChatOnShop($request->user(), $this->customer->shop);
    }

    public function handle(Customer $customer): Customer
    {
        $comms = $customer->comms;

        if ($comms) {
            UpdateCustomerComms::run($comms, collect($comms->getAttributes())
                ->filter(fn ($subscribed, string $column) => str_starts_with($column, 'is_subscribed_to_') && $subscribed)
                ->map(fn () => false)
                ->all());
        }

        return $customer;
    }

    public function asController(Customer $customer, ActionRequest $request): Customer
    {
        $this->customer = $customer;
        $this->initialisationFromShop($customer->shop, $request);

        return $this->handle($customer);
    }

    public function jsonResponse(Customer $customer): JsonResponse
    {
        return response()->json(['subscriptions' => GetCustomerSubscriptions::run($customer->refresh())]);
    }
}
