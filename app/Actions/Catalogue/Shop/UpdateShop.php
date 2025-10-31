<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Fri, 26 Aug 2022 02:04:48 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia F
 */

namespace App\Actions\Catalogue\Shop;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateUniversalSearch;
use App\Actions\Helpers\Address\UpdateAddress;
use App\Actions\Helpers\Media\SaveModelImage;
use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateShops;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateShops;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateShops;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithModelAddressActions;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Helpers\SerialReference\SerialReferenceModelEnum;
use App\Http\Resources\Catalogue\ShopResource;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\SerialReference;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use App\Rules\IUnique;
use App\Rules\ValidAddress;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Lorisleiva\Actions\ActionRequest;

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


        if (count($changes) > 0) {
            ShopHydrateUniversalSearch::dispatch($shop);
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

            'name'                         => ['sometimes', 'required', 'string', 'max:255'],
            'code'                         => [
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
            'contact_name'                 => ['sometimes', 'nullable', 'string', 'max:255'],
            'company_name'                 => ['sometimes', 'nullable', 'string', 'max:255'],
            'email'                        => ['sometimes', 'nullable', 'email'],
            'phone'                        => ['sometimes', 'nullable'],
            'identity_document_number'     => ['sometimes', 'nullable', 'string'],
            'identity_document_type'       => ['sometimes', 'nullable', 'string'],
            'type'                         => ['sometimes', 'required', Rule::enum(ShopTypeEnum::class)],
            'currency_id'                  => ['sometimes', 'required', 'exists:currencies,id'],
            'country_id'                   => ['sometimes', 'required', 'exists:countries,id'],
            'language_id'                  => ['sometimes', 'required', 'exists:languages,id'],
            'timezone_id'                  => ['sometimes', 'required', 'exists:timezones,id'],
            'address'                      => ['sometimes', 'required', new ValidAddress()],
            'collection_address'           => ['sometimes', 'required', new ValidAddress()],
            'state'                        => ['sometimes', Rule::enum(ShopStateEnum::class)],
            'shopify_shop_name'            => ['sometimes', 'string'],
            'shopify_api_key'              => ['sometimes', 'string'],
            'shopify_api_secret'           => ['sometimes', 'string'],
            'shopify_access_token'         => ['sometimes', 'string'],
            'registration_number'          => ['sometimes', 'string'],
            'vat_number'                   => ['sometimes', 'string'],
            'required_approval'            => ['sometimes', 'boolean'],
            'required_phone_number'        => ['sometimes', 'boolean'],
            'marketing_opt_in_default'     => ['sometimes', 'boolean'],
            'marketing_opt_in_label'       => ['sometimes', 'string'],
            'invoice_footer'               => ['sometimes', 'string', 'max:10000'],
            'cost_price_ratio'             => ['sometimes', 'numeric', 'min:0'],
            'price_rrp_ratio'              => ['sometimes', 'numeric', 'min:0'],
            'extra_languages'              => ['sometimes', 'array', 'nullable'],
            'forbidden_dispatch_countries' => ['sometimes', 'array', 'nullable'],
            'image'                        => [
                'sometimes',
                'nullable',
                File::image()
                    ->max(12 * 1024)
            ],
            'colour'                       => ['sometimes', 'string'],
        ];

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
