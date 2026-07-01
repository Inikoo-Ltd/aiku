<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Fri, 26 Aug 2022 02:04:48 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia F
 */

namespace App\Actions\Catalogue\Shop;

use App\Actions\Helpers\Address\UpdateAddress;
use App\Actions\Helpers\Media\SaveModelImage;
use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateShops;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateShops;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateShops;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithModelAddressActions;
use App\Actions\Web\Website\UpdateWebsite;
use App\Enums\Catalogue\Review\ReviewAutoPublishingEnum;
use App\Enums\Catalogue\Review\ReviewContextEnum;
use App\Enums\Catalogue\Review\ReviewRatingDimensionEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Helpers\SerialReference\SerialReferenceModelEnum;
use App\Http\Resources\Catalogue\ShopResource;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\SerialReference;
use App\Models\Inventory\Warehouse;
use App\Models\Reviews\ReviewRatingLabel;
use App\Models\SysAdmin\Organisation;
use App\Rules\IUnique;
use App\Rules\ValidAddress;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Lorisleiva\Actions\ActionRequest;
use App\Models\Ordering\SalesChannel;
use Closure;

class UpdateShop extends OrgAction
{
    use WithActionUpdate;
    use WithModelAddressActions;
    use WithNoStrictRules;


    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo(['org-admin.'.$this->organisation->id, 'shop-admin.'.$this->shop->id]);
    }

    public function handle(Shop $shop, array $modelData): Shop
    {
        if (Arr::exists($modelData, 'review_rating_labels')) {
            $this->syncReviewRatingLabels($shop, Arr::pull($modelData, 'review_rating_labels'));
        }

        $reHydrateChildPrices = false;

        if (Arr::has($modelData, 'invoice_serial_references')) {
            $shop = $this->updateInvoiceSerialReferences($shop, Arr::pull($modelData, 'invoice_serial_references'));
        }

        $oldMasterShop = $shop->masterShop;

        if (Arr::exists($modelData, 'address')) {
            $addressData = Arr::get($modelData, 'address');
            Arr::forget($modelData, 'address');
            $shop = $this->updateModelAddress($shop, $addressData);
        }

        if (Arr::has($modelData, 'image')) {
            $image = Arr::get($modelData, 'image');
            data_forget($modelData, 'image');
            $imageData = [
                'path'         => $image->getPathName(),
                'originalName' => $image->getClientOriginalName(),
                'extension'    => $image->getClientOriginalExtension(),
            ];
            $shop      = SaveModelImage::run(
                model: $shop,
                imageData: $imageData,
                scope: 'avatar'
            );
        }

        if (Arr::has($modelData, 'banned_countries')) {
            $bannedCountries = Arr::pull($modelData, 'banned_countries');
            data_set($modelData, 'banned_country_regions', Arr::get($bannedCountries, 'banned_list', []));
            data_set($modelData, 'settings.banned_countries.is_follow_organisation_banned_list', (bool)Arr::get($bannedCountries, 'is_follow_organisation_banned_list', false));
        }

        if (Arr::has($modelData, 'dispatch_require_shipping')) {
            data_set($modelData, 'settings.dispatch.require_shipping', Arr::pull($modelData, 'dispatch_require_shipping'));
        }

        if (Arr::has($modelData, 'identity_document_number_label')) {
            data_set($modelData, 'settings.customer.identity_document_number', Arr::pull($modelData, 'identity_document_number_label'));
        }

        if (Arr::has($modelData, 'identity_document_number_alt_label')) {
            data_set($modelData, 'settings.customer.identity_document_number_alt', Arr::pull($modelData, 'identity_document_number_alt_label'));
        }

        // Catalogue Descriptions etc

        if (Arr::has($modelData, 'collection_follow_master')) {
            data_set($modelData, 'settings.catalog.collection_follow_master', Arr::pull($modelData, 'collection_follow_master'));
        }

        if (Arr::has($modelData, 'department_follow_master')) {
            data_set($modelData, 'settings.catalog.department_follow_master', Arr::pull($modelData, 'department_follow_master'));
        }

        if (Arr::has($modelData, 'sub_department_follow_master')) {
            data_set($modelData, 'settings.catalog.sub_department_follow_master', Arr::pull($modelData, 'sub_department_follow_master'));
        }

        if (Arr::has($modelData, 'family_follow_master')) {
            data_set($modelData, 'settings.catalog.family_follow_master', Arr::pull($modelData, 'family_follow_master'));
        }

        if (Arr::has($modelData, 'product_follow_master')) {
            data_set($modelData, 'settings.catalog.product_follow_master', Arr::pull($modelData, 'product_follow_master'));
        }

        if (Arr::has($modelData, 'related_product_follow_master')) {
            data_set($modelData, 'settings.catalog.related_product_follow_master', Arr::pull($modelData, 'related_product_follow_master'));
        }

        if (Arr::has($modelData, 'related_product_categories_follow_master')) {
            data_set($modelData, 'settings.catalog.related_product_categories_follow_master', Arr::pull($modelData, 'related_product_categories_follow_master'));
        }

        if (Arr::has($modelData, 'follow_master_pricing')) {
            $reHydrateChildPrices = true;
            data_set($modelData, 'settings.catalog.follow_master_pricing', Arr::pull($modelData, 'follow_master_pricing'));
        }

        // Catalogue Indexing etc

        if (Arr::has($modelData, 'family_indexing_follow_master')) {
            data_set($modelData, 'settings.catalog.family_indexing_follow_master', Arr::pull($modelData, 'family_indexing_follow_master'));
        }

        if (Arr::exists($modelData, 'portal_link')) {
            if (Arr::get($modelData, 'portal_link') === null) {
                data_set($modelData, 'portal_link', '');
            }
        }


        foreach ($modelData as $key => $value) {
            data_set(
                $modelData,
                match ($key) {
                    'shopify_shop_name' => 'settings.shopify.shop_name',
                    'shopify_api_key' => 'settings.shopify.api_key',
                    'shopify_api_secret' => 'settings.shopify.api_secret',
                    'shopify_access_token' => 'settings.shopify.access_token',
                    'registration_number' => 'data.registration_number',
                    'vat_number' => 'data.vat_number',
                    'ebay_redirect_key' => 'settings.ebay.redirect_key',
                    'ebay_marketplace_id' => 'settings.ebay.marketplace_id',
                    'ebay_warehouse_city' => 'settings.ebay.warehouse_city',
                    'ebay_warehouse_state' => 'settings.ebay.warehouse_state',
                    'ebay_warehouse_country' => 'settings.ebay.warehouse_country',
                    'faire_access_token' => 'settings.faire.access_token',
                    'faire_order_from_days' => 'settings.faire.order_from_days',
                    'faire_is_shipping_by_external' => 'settings.faire.is_shipping_by_external',
                    'faire_dont_send_first_orders_automatically_to_warehouse' => 'settings.faire.dont_send_first_orders_automatically_to_warehouse',
                    'wix_access_token' => 'settings.wix.access_token',
                    'gads_customer_id' => 'settings.google_ads.customer_id',
                    'gads_login_customer_id' => 'settings.google_ads.login_customer_id',
                    'gads_user_list_id' => 'settings.google_ads.user_list_id',
                    'enable_chat'          => 'settings.chat.enable_chat',
                    'portal_link' => 'settings.portal.link',
                    'review_rating_labels' => 'settings.reviews.rating_labels',
                    'bank_transfer_instructions_for_email' => 'settings.bank_transfer_instructions_for_email',
                    default => $key
                },
                $value
            );
        }

        data_forget($modelData, 'shopify_shop_name');
        data_forget($modelData, 'shopify_api_key');
        data_forget($modelData, 'shopify_api_secret');
        data_forget($modelData, 'shopify_access_token');
        data_forget($modelData, 'registration_number');
        data_forget($modelData, 'vat_number');
        data_forget($modelData, 'ebay_redirect_key');
        data_forget($modelData, 'ebay_marketplace_id');
        data_forget($modelData, 'ebay_warehouse_country');
        data_forget($modelData, 'ebay_warehouse_city');
        data_forget($modelData, 'ebay_warehouse_state');
        data_forget($modelData, 'faire_access_token');
        data_forget($modelData, 'faire_order_from_days');
        data_forget($modelData, 'faire_is_shipping_by_external');
        data_forget($modelData, 'faire_dont_send_first_orders_automatically_to_warehouse');
        data_forget($modelData, 'is_shipping_by_external');
        data_forget($modelData, 'wix_access_token');
        data_forget($modelData, 'gads_customer_id');
        data_forget($modelData, 'gads_login_customer_id');
        data_forget($modelData, 'gads_user_list_id');
        data_forget($modelData, 'portal_link');

        if (Arr::exists($modelData, 'chat_slack_token') || Arr::exists($modelData, 'chat_slack_channels')) {
            $settings = $shop->settings ?? [];

            if (Arr::exists($modelData, 'chat_slack_token')) {
                $token = Arr::pull($modelData, 'chat_slack_token');
                if (!empty($token)) {
                    data_set($settings, 'chat.slack_token', $token);
                }
            }

            if (Arr::exists($modelData, 'chat_slack_channels')) {
                $channels = array_values(array_filter((array) Arr::pull($modelData, 'chat_slack_channels')));
                data_set($settings, 'chat.slack_channels', $channels);
            }

            $shop->settings = $settings;
            $shop->saveQuietly();
        }

        if (Arr::exists($modelData, 'enable_chat')) {
            $enableChat = Arr::pull($modelData, 'enable_chat');
            UpdateWebsite::make()->action(
                website: $shop->website,
                modelData: ['enable_chat' => $enableChat],
                strict: false
            );
        }

        if (Arr::exists($modelData, 'widget_key')) {
            $widgetKey = Arr::pull($modelData, 'widget_key');
            if ($widgetKey === null) {
                $widgetKey = '';
            }
            UpdateWebsite::make()->action(
                website: $shop->website,
                modelData: ['jira_help_desk_widget' => $widgetKey],
                strict: false
            );
        }

        $currentChannelIds = $shop->salesChannels()->allRelatedIds()->toArray();
        $hasChannelUpdates = false;
        $keys              = array_keys($modelData);
        foreach ($keys as $key) {
            if (str_starts_with($key, 'sales_channel_')) {
                $hasChannelUpdates = true;
                $channelId         = (int)str_replace('sales_channel_', '', $key);
                $isActive          = $modelData[$key];
                if ($isActive === true) {
                    if (!in_array($channelId, $currentChannelIds)) {
                        $currentChannelIds[] = $channelId;
                    }
                } else {
                    $currentChannelIds = array_diff($currentChannelIds, [$channelId]);
                }
                unset($modelData[$key]);
            }
        }
        if ($hasChannelUpdates) {
            $shop->salesChannels()->sync(array_values($currentChannelIds));
        }

        if (Arr::exists($modelData, 'collection_address')) {
            $collectionAddressData = Arr::get($modelData, 'collection_address');
            Arr::forget($modelData, 'collection_address');

            if ($shop->collection_address_id) {
                UpdateAddress::run($shop->collectionAddress, $collectionAddressData);
            } else {
                return $this->addAddressToModelFromArray(model: $shop, addressData: $collectionAddressData, updateLocation: false, updateAddressField: 'collection_address_id');
            }
        }

        if (Arr::exists($modelData, 'required_approval')) {
            data_set($modelData, "settings.registration.require_approval", Arr::pull($modelData, 'required_approval'));
        }

        if (Arr::exists($modelData, 'required_phone_number')) {
            data_set($modelData, "settings.registration.require_phone_number", Arr::pull($modelData, 'required_phone_number'));
        }

        if (Arr::exists($modelData, 'marketing_opt_in_label')) {
            data_set($modelData, "settings.registration.marketing_opt_in_label", Arr::pull($modelData, 'marketing_opt_in_label'));
        }

        if (Arr::exists($modelData, 'marketing_opt_in_default')) {
            data_set($modelData, "settings.registration.marketing_opt_in_default", Arr::pull($modelData, 'marketing_opt_in_default'));
        }

        if (Arr::exists($modelData, 'stand_alone_invoice_numbers')) {
            data_set($modelData, "settings.invoicing.stand_alone_invoice_numbers", Arr::pull($modelData, 'stand_alone_invoice_numbers'));
        }

        if (Arr::exists($modelData, 'download_pdf_columns')) {
            $columnsMap = [];
            foreach (Arr::pull($modelData, 'download_pdf_columns') as $col) {
                $columnsMap[$col['key']] = (bool)$col['value'];
            }
            data_set($modelData, "settings.invoicing.download_pdf_columns", $columnsMap);
        }

        if (Arr::exists($modelData, 'reviews')) {
            data_set($modelData, 'settings.reviews.enabled', (bool) Arr::pull($modelData, 'reviews'));
        }

        if (Arr::exists($modelData, 'review_visibility')) {
            $visibility = Arr::pull($modelData, 'review_visibility');
            data_set($modelData, 'settings.reviews.visibility.private', (bool) data_get($visibility, 'visibility.private', false));
            data_set($modelData, 'settings.reviews.visibility.public', (bool) data_get($visibility, 'visibility.public', true));
        }

        if (Arr::exists($modelData, 'review_publishing')) {
            $reviewPublishing   = Arr::pull($modelData, 'review_publishing');
            $autoPublishingMode = Arr::get($reviewPublishing, 'auto_publishing.mode');

            data_set($modelData, 'settings.reviews.auto_publishing.mode', $autoPublishingMode);
            data_set(
                $modelData,
                'settings.reviews.auto_publishing.delay_hours',
                $autoPublishingMode === ReviewAutoPublishingEnum::DELAY->value
                    ? (int) Arr::get($reviewPublishing, 'auto_publishing.delay_hours', 24)
                    : null
            );

            if (Arr::has($shop->settings ?? [], 'reviews.auto_publishing.delay')) {
                $settings = $shop->settings;
                Arr::forget($settings, 'reviews.auto_publishing.delay');
                $shop->updateQuietly(['settings' => $settings]);
                $shop->refresh();
            }
        }

        if (Arr::exists($modelData, 'review_public_rating_threshold')) {
            data_set($modelData, 'settings.reviews.public_rating_threshold', (int) Arr::pull($modelData, 'review_public_rating_threshold'));
        }

        if (Arr::exists($modelData, 'review_minimum_rating_to_show')) {
            data_set($modelData, 'settings.reviews.minimum_rating_to_show', (int) Arr::pull($modelData, 'review_minimum_rating_to_show'));
        }

        if (Arr::exists($modelData, 'review_minimum_reviews_to_show')) {
            data_set($modelData, 'settings.reviews.minimum_reviews_to_show', (int) Arr::pull($modelData, 'review_minimum_reviews_to_show'));
        }

        if (Arr::exists($modelData, 'review_show_staff_who_reply')) {
            data_set($modelData, 'settings.reviews.show_staff_who_reply', (bool) Arr::pull($modelData, 'review_show_staff_who_reply'));
        }

        if (Arr::exists($modelData, 'review_approval_required')) {
            data_set($modelData, 'settings.reviews.data.approval_required', (bool) Arr::pull($modelData, 'review_approval_required'));
        }

        if (Arr::exists($modelData, 'review_hours_after_dispatched')) {
            data_set($modelData, 'settings.reviews.data.hours_after_dispatched', (int) Arr::pull($modelData, 'review_hours_after_dispatched'));
        }

        if (Arr::exists($modelData, 'review_allow_reactions')) {
            data_set($modelData, 'settings.reviews.allow_reactions', Arr::pull($modelData, 'review_allow_reactions'));
        }

        if (Arr::exists($modelData, 'review_allow_reply_reactions')) {
            data_set($modelData, 'settings.reviews.allow_reply_reactions', Arr::pull($modelData, 'review_allow_reply_reactions'));
        }

        $shop    = $this->update($shop, $modelData, ['data', 'settings']);
        $changes = $shop->getChanges();
        $shop->refresh();

        if (Arr::hasAny($changes, ['state', 'type'])) {
            GroupHydrateShops::dispatch($shop->group)->delay($this->hydratorsDelay);
            OrganisationHydrateShops::dispatch($shop->organisation)->delay($this->hydratorsDelay);
            if ($shop->master_shop_id) {
                MasterShopHydrateShops::dispatch($shop->masterShop)->delay($this->hydratorsDelay);
            }
        }

        if (Arr::hasAny($changes, ['master_shop_id'])) {
            if ($shop->master_shop_id) {
                MasterShopHydrateShops::dispatch($shop->masterShop)->delay($this->hydratorsDelay);
            }
            if ($oldMasterShop) {
                MasterShopHydrateShops::dispatch($oldMasterShop)->delay($this->hydratorsDelay);
            }
        }

        if ($reHydrateChildPrices) {
            // TODO MasterLevel Price RRP (Raul)
            // TODO Rehydrate Child Prices according to their master counterpart prices & rrp here
        }

        return $shop;
    }

    public function updateInvoiceSerialReferences(Shop $shop, array $modelData): Shop
    {
        $invoiceSerialReference = SerialReference::where('model', SerialReferenceModelEnum::INVOICE)
            ->where('container_type', 'Shop')
            ->where('container_id', $shop->id)->first();

        $invoiceSerialReference->updateQuietly(
            [
                'format' => $modelData['stand_alone_invoice_numbers_format'],
                'serial' => $modelData['stand_alone_invoice_numbers_serial'],
            ]
        );


        $refundSerialReference = SerialReference::where('model', SerialReferenceModelEnum::REFUND)
            ->where('container_type', 'Shop')
            ->where('container_id', $shop->id)->first();

        $refundSerialReference->updateQuietly(
            [
                'format' => $modelData['stand_alone_refund_numbers_format'],
                'serial' => $modelData['stand_alone_refund_numbers_serial'],
            ]
        );

        $settings = $shop->settings;
        data_set($settings, 'invoicing.stand_alone_invoice_numbers', $modelData['stand_alone_invoice_numbers']);
        data_set($settings, 'invoicing.stand_alone_refund_numbers', $modelData['stand_alone_refund_numbers']);

        $shop->updateQuietly([
            'settings' => $settings,
        ]);
        $shop->refresh();

        return $shop;
    }

    protected function syncReviewRatingLabels(Shop $shop, ?array $reviewRatingLabels): void
    {
        $baseQuery = ReviewRatingLabel::query()
            ->where('model_type', 'shop')
            ->where('model_id', $shop->id);

        if ($reviewRatingLabels === null) {
            $baseQuery->delete();

            return;
        }

        $keepKeys = [];

        foreach (ReviewContextEnum::values() as $reviewContext) {
            foreach (ReviewRatingDimensionEnum::values() as $index => $dimension) {
                $label = trim((string) data_get($reviewRatingLabels, "$reviewContext.$dimension", ''));

                if ($label === '') {
                    continue;
                }

                $keepKey            = "$reviewContext:$dimension";
                $keepKeys[$keepKey] = true;

                ReviewRatingLabel::query()->updateOrCreate(
                    [
                        'model_type' => 'shop',
                        'model_id' => $shop->id,
                        'review_context' => $reviewContext,
                        'dimension' => $dimension,
                    ],
                    [
                        'label' => $label,
                        'sort_order' => $index,
                        'is_active' => true,
                    ]
                );
            }
        }

        $baseQuery
            ->get()
            ->each(function (ReviewRatingLabel $reviewRatingLabel) use ($keepKeys): void {
                $reviewContext = $reviewRatingLabel->review_context instanceof ReviewContextEnum
                    ? $reviewRatingLabel->review_context->value
                    : (string) $reviewRatingLabel->review_context;
                $dimension = $reviewRatingLabel->dimension instanceof ReviewRatingDimensionEnum
                    ? $reviewRatingLabel->dimension->value
                    : (string) $reviewRatingLabel->dimension;
                $key = "$reviewContext:$dimension";

                if (!isset($keepKeys[$key])) {
                    $reviewRatingLabel->delete();
                }
            });
    }

    public function rules(): array
    {
        $rules = [
            'invoice_serial_references'   => ['sometimes', 'array'],
            'registration_needs_approval' => ['sometimes', 'boolean'],
            'stand_alone_invoice_numbers' => ['sometimes', 'boolean'],
            'master_shop_id'              => [
                'sometimes',
                'nullable',
                Rule::Exists('master_shops', 'id')->where('group_id', $this->organisation->group_id)

            ],

            'name'                                                    => ['sometimes', 'required', 'string', 'max:255'],
            'code'                                                    => [
                'sometimes',
                'required',
                'max:8',
                'alpha_dash',
                new IUnique(
                    table: 'shops',
                    extraConditions: [

                        ['column' => 'group_id', 'value' => $this->organisation->group_id],
                        [
                            'column'   => 'id',
                            'operator' => '!=',
                            'value'    => $this->shop->id
                        ],
                    ]
                ),

            ],
            'contact_name'                                            => ['sometimes', 'nullable', 'string', 'max:255'],
            'company_name'                                            => ['sometimes', 'nullable', 'string', 'max:255'],
            'email'                                                   => ['sometimes', 'nullable', 'email'],
            'phone'                                                   => ['sometimes', 'nullable'],
            'identity_document_number_label'                          => ['sometimes', 'nullable', 'string'],
            'identity_document_number_alt_label'                      => ['sometimes', 'nullable', 'string'],
            'identity_document_number'                                => ['sometimes', 'nullable', 'string'],
            'identity_document_number_alt'                            => ['sometimes', 'nullable', 'string'],
            'identity_document_type'                                  => ['sometimes', 'nullable', 'string'],
            'type'                                                    => ['sometimes', 'required', Rule::enum(ShopTypeEnum::class)],
            'currency_id'                                             => ['sometimes', 'required', 'exists:currencies,id'],
            'country_id'                                              => ['sometimes', 'required', 'exists:countries,id'],
            'language_id'                                             => ['sometimes', 'required', 'exists:languages,id'],
            'timezone_id'                                             => ['sometimes', 'required', 'exists:timezones,id'],
            'address'                                                 => ['sometimes', 'required', new ValidAddress()],
            'collection_address'                                      => ['sometimes', 'required', new ValidAddress()],
            'state'                                                   => ['sometimes', Rule::enum(ShopStateEnum::class)],
            'shopify_shop_name'                                       => ['sometimes', 'string'],
            'shopify_api_key'                                         => ['sometimes', 'string'],
            'shopify_api_secret'                                      => ['sometimes', 'string'],
            'shopify_access_token'                                    => ['sometimes', 'string'],
            'registration_number'                                     => ['sometimes', 'string'],
            'vat_number'                                              => ['sometimes', 'string'],
            'ebay_redirect_key'                                       => ['sometimes', 'string'],
            'ebay_marketplace_id'                                     => ['sometimes', 'string'],
            'ebay_warehouse_city'                                     => ['sometimes', 'string'],
            'ebay_warehouse_state'                                    => ['sometimes', 'string'],
            'ebay_warehouse_country'                                  => ['sometimes', 'string'],
            'faire_access_token'                                      => ['sometimes', 'string'],
            'faire_order_from_days'                                   => ['sometimes', 'string'],
            'faire_is_shipping_by_external'                           => ['sometimes', 'boolean'],
            'faire_dont_send_first_orders_automatically_to_warehouse' => ['sometimes', 'boolean'],
            'wix_access_token'                                        => ['sometimes', 'string'],
            'gads_customer_id'                                  => ['sometimes', 'nullable', 'string'],
            'gads_login_customer_id'                            => ['sometimes', 'nullable', 'string'],
            'gads_user_list_id'                                 => ['sometimes', 'nullable', 'string'],
            'enable_chat'                                             => ['sometimes', 'boolean'],
            'chat_slack_token'                                        => ['sometimes', 'nullable', 'string'],
            'chat_slack_channels'                                     => ['sometimes', 'nullable', 'array'],
            'chat_slack_channels.*'                                   => ['string'],
            'is_shipping_by_external'                                 => ['sometimes', 'boolean'],
            'portal_link'                                             => ['sometimes', 'nullable', 'string'],
            'widget_key'                                              => ['sometimes', 'nullable', 'string'],
            'required_approval'                                       => ['sometimes', 'boolean'],
            'required_phone_number'                                   => ['sometimes', 'boolean'],
            'marketing_opt_in_default'                                => ['sometimes', 'boolean'],
            'marketing_opt_in_label'                                  => ['sometimes', 'string'],
            'invoice_footer'                                          => ['sometimes', 'string', 'max:10000'],
            'download_pdf_columns'                                    => ['sometimes', 'array'],
            'cost_price_ratio'                                        => ['sometimes', 'numeric', 'min:0'],
            'price_rrp_ratio'                                         => ['sometimes', 'numeric', 'min:0'],
            'extra_languages'                                         => ['sometimes', 'array', 'nullable'],
            'image'                                                   => [
                'sometimes',
                'nullable',
                File::image()
                    ->max(12 * 1024)
            ],
            'colour'                                                  => ['sometimes', 'string'],
            'collection_follow_master'                                => ['sometimes', 'boolean'],
            'department_follow_master'                                => ['sometimes', 'boolean'],
            'sub_department_follow_master'                            => ['sometimes', 'boolean'],
            'family_follow_master'                                    => ['sometimes', 'boolean'],
            'product_follow_master'                                   => ['sometimes', 'boolean'],
            'related_product_follow_master'                           => ['sometimes', 'boolean'],
            'related_product_categories_follow_master'                => ['sometimes', 'boolean'],
            'family_indexing_follow_master'                           => ['sometimes', 'boolean'],
            'product_price_currency_exchange'                         => ['sometimes', 'numeric', 'min:0'],
            'proforma_footer'                                         => ['sometimes', 'string', 'max:10000'],
            'family_webpage_split_description'                        => ['sometimes', 'boolean'],
            'reviews'                                                 => ['sometimes', 'boolean'],
            'review_rating_labels'                                    => ['sometimes', 'nullable', 'array'],
            'review_rating_labels.*'                                  => ['sometimes', 'array'],
            'review_rating_labels.*.*'                                => ['sometimes', 'nullable', 'string', 'max:255'],
            'review_visibility'                                       => ['sometimes', 'nullable', 'array'],
            'review_visibility.visibility.private'                    => ['sometimes', 'boolean'],
            'review_visibility.visibility.public'                     => ['sometimes', 'boolean'],
            'review_publishing'                                       => ['sometimes', 'nullable', 'array'],
            'review_publishing.auto_publishing.mode'                  => ['sometimes', 'required', Rule::enum(ReviewAutoPublishingEnum::class)],
            'review_publishing.auto_publishing.delay_hours'           => ['sometimes', 'nullable', 'integer', 'min:1', 'required_if:review_publishing.auto_publishing.mode,'.ReviewAutoPublishingEnum::DELAY->value],
            'review_public_rating_threshold'                          => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5'],
            'review_minimum_rating_to_show'                           => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5'],
            'review_minimum_reviews_to_show'                          => ['sometimes', 'nullable', 'integer', 'min:0'],
            'review_show_staff_who_reply'                             => ['sometimes', 'boolean'],
            'review_approval_required'                                => ['sometimes', 'boolean'],
            'review_hours_after_dispatched'                           => ['sometimes', 'nullable', 'integer', 'min:1'],
            'review_allow_reactions'                                  => ['sometimes', 'boolean'],
            'review_allow_reply_reactions'                            => ['sometimes', 'boolean'],
            'dispatch_require_shipping'                               => ['sometimes', 'boolean'],
            'bank_transfer_instructions_for_email'                    => ['sometimes', 'nullable', 'string', 'max:10000'],
            'follow_master_pricing'                                   => ['sometimes', 'boolean'],
            'banned_countries'                                        => ['sometimes', 'nullable', 'array'],
            'banned_countries.is_follow_organisation_banned_list'     => ['sometimes', 'boolean'],
            'banned_countries.banned_list'                            => ['sometimes', 'nullable', 'array'],
            'banned_countries.banned_list.*'                          => ['required', 'array'],
            'banned_countries.banned_list.*.postcode'                 => [
                'sometimes', 
                'string', 
                'nullable',
                function (string $attribute, mixed $value, Closure $fail) {
                    if ($value === null || $value === '') {
                        return;
                    }

                    // Just to check whether valid regex or not. Would throw false if it's an invalid regex since preg_match would not compile
                    if (@preg_match($value, '') === false) {
                        $fail('Invalid Postcode regex');
                    }
                },
            ],
            'banned_countries.banned_list.*.billing'                  => ['required', 'boolean'],
            'banned_countries.banned_list.*.delivery'                 => ['required', 'boolean'],
        ];

        $channelIds = SalesChannel::pluck('id');
        foreach ($channelIds as $id) {
            $rules['sales_channel_'.$id] = ['sometimes', 'boolean'];
        }

        if (!$this->strict) {
            $rules = $this->noStrictUpdateRules($rules);
        }

        return $rules;
    }

    public function action(Shop $shop, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): Shop
    {
        if (!$audit) {
            Warehouse::disableAuditing();
        }
        $this->asAction       = true;
        $this->shop           = $shop;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->strict         = $strict;
        $this->initialisation($shop->organisation, $modelData);

        return $this->handle($shop, $this->validatedData);
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): Shop
    {
        $this->shop = $shop;
        $this->initialisation($organisation, $request);

        return $this->handle($shop, $this->validatedData);
    }

    public function jsonResponse(Shop $shop): ShopResource
    {
        return new ShopResource($shop);
    }

}
