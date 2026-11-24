<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 26 Jun 2024 15:13:29 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\CustomerSalesChannel;

use App\Actions\Catalogue\ShopPlatformStats\ShopPlatformStatsHydrateCustomers;
use App\Actions\Catalogue\ShopPlatformStats\ShopPlatformStatsHydrateCustomerSalesChannel;
use App\Actions\Dropshipping\Platform\Hydrators\PlatformHydrateCustomers;
use App\Actions\OrgAction;
use App\Enums\Dropshipping\CustomerSalesChannelStateEnum;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Http\Middleware\SetWebUserLocale;
use App\Models\CRM\Customer;
use App\Models\Helpers\Language;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreCustomerSalesChannel extends OrgAction
{
    public function handle(Customer $customer, Platform $platform, array $modelData): CustomerSalesChannel
    {
        if ($platform->type == PlatformTypeEnum::MANUAL) {
            data_set($modelData, 'can_connect_to_platform', true);
            data_set($modelData, 'exist_in_platform', true);
            data_set($modelData, 'platform_status', true);
        }

        data_set($modelData, 'status', CustomerSalesChannelStatusEnum::OPEN, overwrite: false);

        $modelData['group_id']          = $customer->group_id;
        $modelData['organisation_id']   = $customer->organisation_id;
        $modelData['shop_id']           = $customer->shop_id;
        $modelData['platform_id']       = $platform->id;

        if ($customer->shop->customersChooseChannelLanguage()) {
            data_set($modelData, 'language_id', $this->customerLanguageId($customer), overwrite: false);
        }

        /** @var CustomerSalesChannel $customerSalesChannel */
        $customerSalesChannel           = $customer->customerSalesChannels()->create($modelData);

        PlatformHydrateCustomers::dispatch($platform)->delay($this->hydratorsDelay);
        ShopPlatformStatsHydrateCustomers::dispatch($customer->shop, $platform)->delay($this->hydratorsDelay);
        ShopPlatformStatsHydrateCustomerSalesChannel::dispatch($customer->shop, $platform)->delay($this->hydratorsDelay);


        return $customerSalesChannel;
    }

    public const string NEW_CHANNEL_LANGUAGE_SESSION_KEY = 'retina_new_channel_language_id';

    /**
     * The language the customer picked on the create channel page, else the language they are reading the site in
     * (a ?locale they opened /app with, else their main web user's language), when the shop offers it.
     * The pick is also kept against the customer, because the TikTok, Allegro and Wix OAuth callbacks land on the
     * aiku domain, where the customer's retina session is not sent.
     */
    public function customerLanguageId(Customer $customer): ?int
    {
        $offeredLanguageIds = $customer->shop->languageIdsFor($customer);

        foreach ($this->pickedLanguageIds($customer) as $languageId) {
            if (in_array($languageId, $offeredLanguageIds)) {
                return $languageId;
            }
        }

        $languageId = $customer->webUsers()->orderByDesc('is_root')->orderBy('id')->value('language_id');

        return in_array($languageId, $offeredLanguageIds) ? $languageId : null;
    }

    public static function rememberNewChannelLanguage(Customer $customer, int $languageId): void
    {
        if (request()->hasSession()) {
            request()->session()->put(self::NEW_CHANNEL_LANGUAGE_SESSION_KEY, $languageId);
        }

        Cache::put(self::newChannelLanguageCacheKey($customer), $languageId, now()->addMinutes((int) config('session.lifetime')));
    }

    private static function newChannelLanguageCacheKey(Customer $customer): string
    {
        return 'new-channel-language-'.$customer->id;
    }

    /**
     * @return array<int, int>
     */
    private function pickedLanguageIds(Customer $customer): array
    {
        $session = request()->hasSession() ? request()->session() : null;

        return array_values(array_filter([
            (int) $session?->get(self::NEW_CHANNEL_LANGUAGE_SESSION_KEY),
            (int) Cache::get(self::newChannelLanguageCacheKey($customer)),
            (int) Language::where('code', $session?->get(SetWebUserLocale::ADDRESS_LOCALE_SESSION_KEY))->value('id'),
        ]));
    }

    public function rules(): array
    {
        return [
            'name'               => 'nullable|string|max:255',
            'reference'          => 'nullable|string|max:255',
            'platform_user_type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'platform_user_id'   => ['sometimes', 'nullable', 'integer'],
            'state'              => ['sometimes', 'nullable', Rule::enum(CustomerSalesChannelStateEnum::class)],
        ];
    }


    public function action(Customer $customer, Platform $platform, array $modelData, int $hydratorsDelay = 0): CustomerSalesChannel
    {
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisation($customer->organisation, $modelData);

        return $this->handle($customer, $platform, $this->validatedData);
    }

    public function asController(Customer $customer, Platform $platform, ActionRequest $request): void
    {
        $this->initialisation($customer->organisation, $request);
        $this->handle($customer, $platform, $this->validatedData);
    }
}
