<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Iris\ShoppingAssistant;

use App\Actions\CRM\ShoppingAssistant\GetShoppingAssistantSettings;
use App\Actions\CRM\ShoppingAssistant\StartShoppingAssistantConversation;
use App\Actions\IrisAction;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantEntryPointEnum;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantEventTypeEnum;
use App\Models\CRM\Customer;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantConversation;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\ActionRequest;

/**
 * A list of the Range Advisor panel seen (its products recorded as `shown`) or one of its products
 * clicked, only for products the panel offered this customer in the last day. Panel events go on one
 * conversation per customer, website and day with entry point `panel` and no messages, so basket adds
 * and orders of what the panel showed are attributed like the chat's.
 */
class StoreIrisShoppingAssistantPanelEvent extends IrisAction
{
    /**
     * @param array{type: string, list: string, product_ids?: array<int, int>, product_id?: int} $modelData
     */
    public function handle(Customer $customer, array $modelData, ?int $webUserId = null): void
    {
        $offered = Arr::get(Cache::get(GetIrisShoppingAssistantPanel::offeredCacheKey($this->website->id, $customer->id), []), $modelData['list'], []);
        $ids     = $modelData['type'] === ShoppingAssistantEventTypeEnum::SHOWN->value
            ? array_map('intval', $modelData['product_ids'] ?? [])
            : [(int) ($modelData['product_id'] ?? 0)];
        $ids = array_values(array_filter(array_unique($ids), fn (int $id) => isset($offered[$id])));
        if (!$ids) {
            return;
        }

        $conversation = $this->panelConversation($customer, $webUserId);

        if ($modelData['type'] === ShoppingAssistantEventTypeEnum::SHOWN->value) {
            $alreadyShown = $conversation->events()
                ->where('type', ShoppingAssistantEventTypeEnum::SHOWN)
                ->where('group', $modelData['list'])
                ->whereIn('product_id', $ids)
                ->pluck('product_id')
                ->flip();
            foreach ($ids as $id) {
                if (!$alreadyShown->has($id)) {
                    $conversation->events()->create([
                        'type'        => ShoppingAssistantEventTypeEnum::SHOWN,
                        'product_id'  => $id,
                        'group'       => $modelData['list'],
                        ...$offered[$id],
                    ]);
                }
            }

            return;
        }

        $conversation->events()->firstOrCreate([
            'type'       => ShoppingAssistantEventTypeEnum::CLICKED,
            'product_id' => $ids[0],
            'group'      => $modelData['list'],
        ], Arr::only($offered[$ids[0]], ['position', 'reason_code']));
    }

    private function panelConversation(Customer $customer, ?int $webUserId): ShoppingAssistantConversation
    {
        return ShoppingAssistantConversation::query()
            ->where('customer_id', $customer->id)
            ->where('website_id', $this->website->id)
            ->where('entry_point', ShoppingAssistantEntryPointEnum::PANEL)
            ->where('created_at', '>=', now()->startOfDay())
            ->orderBy('id')
            ->first()
            ?? StartShoppingAssistantConversation::run($customer, ShoppingAssistantEntryPointEnum::PANEL, ['web_user_id' => $webUserId]);
    }

    public function rules(): array
    {
        return [
            'type'          => ['required', 'string', 'in:'.ShoppingAssistantEventTypeEnum::SHOWN->value.','.ShoppingAssistantEventTypeEnum::CLICKED->value],
            'list'          => ['required', 'string', 'in:'.implode(',', GetIrisShoppingAssistantPanel::LISTS)],
            'product_ids'   => ['required_if:type,shown', 'array', 'max:20'],
            'product_ids.*' => ['integer'],
            'product_id'    => ['required_if:type,clicked', 'integer'],
        ];
    }

    public function asController(ActionRequest $request): Response
    {
        $this->initialisation($request);

        $customerId = $this->signedInCustomerId();
        $customer   = $customerId ? Customer::find($customerId) : null;
        abort_unless($customer && GetShoppingAssistantSettings::run($this->website, $customer), 404);

        $this->handle($customer, $this->validatedData, $request->user()?->id);

        return response()->noContent();
    }
}
