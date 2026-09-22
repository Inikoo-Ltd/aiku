<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\PhoneCall\Json;

use App\Actions\Chat\PhoneCall\WithChatPhoneCall;
use App\Actions\CRM\Customer\Json\GetCustomersInShop;
use App\Models\Catalogue\Shop;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use App\Http\Resources\CRM\CustomersForSelectResource;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Naming who was on the telephone is part of working chat, but the customer list an agent reaches
 * through the catalogue asks for catalogue permissions they have no reason to hold, so it answers
 * "unauthorized" to the person the call belongs to. The same query is served here behind the rule
 * the rest of the feature uses: the shops this agent works, and no others.
 */
class GetChatPhoneCallCustomers
{
    use AsAction;
    use WithChatPhoneCall;

    public function handle(Shop $shop): LengthAwarePaginator
    {
        return GetCustomersInShop::make()->handle($shop);
    }

    public function asController(Shop $shop): LengthAwarePaginator
    {
        return $this->handle($shop);
    }

    public function authorize(ActionRequest $request): bool
    {
        $shop = $request->route('shop');

        return $shop instanceof Shop
            && in_array($shop->id, $this->workableShopIdsFor($request->user()), true);
    }

    public function jsonResponse(LengthAwarePaginator $customers): AnonymousResourceCollection
    {
        return CustomersForSelectResource::collection($customers);
    }
}
