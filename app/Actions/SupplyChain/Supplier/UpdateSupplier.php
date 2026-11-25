<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 03 Apr 2024 20:49:22 Central Indonesia Time, Bali Office, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\Supplier;

use App\Actions\Accounting\EInvoice\BuildUblInvoice;
use App\Actions\SupplyChain\SupplierPortalContact\SyncSupplierPortalContact;
use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateCurrentSupplierSkuCost;
use App\Actions\OrgAction;
use App\Actions\SupplyChain\SupplierProduct\UpdateSupplierProduct;
use App\Actions\Helpers\Address\UpdateAddress;
use App\Actions\Helpers\Media\SaveModelImage;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Http\Resources\SupplyChain\SupplierResource;
use App\Models\Helpers\Currency;
use App\Models\Inventory\OrgStock;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\Supplier;
use App\Models\SysAdmin\User;
use App\Rules\IUnique;
use App\Rules\Phone;
use App\Rules\ValidAddress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class UpdateSupplier extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithSupplierJsonColumns;
    use WithSupplierPacking;


    private Supplier $supplier;
    private bool $action = false;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        if ($request->has('agent_id')) {
            return $request->user()->authTo('supply-chain.edit');
        }

        return self::canBeEditedBy($this->supplier, $request->user());
    }

    public static function canBeEditedBy(Supplier $supplier, User $user): bool
    {
        if ($supplier->agent && $user->authTo("procurement.{$supplier->agent->organisation_id}.edit")) {
            return true;
        }

        foreach ($supplier->orgSuppliers()->pluck('organisation_id') as $organisationId) {
            if ($user->authTo("procurement.{$organisationId}.edit")) {
                return true;
            }
        }

        return $user->authTo('supply-chain.edit');
    }

    public function handle(Supplier $supplier, array $modelData): Supplier
    {
        $leavingBillOfLading = $this->isLeavingBillOfLadingDelivery($modelData);

        if ($leavingBillOfLading) {
            Arr::forget($modelData, self::BILL_OF_LADING_FIELDS);
        }

        if (Arr::has($modelData, 'agent_id')) {
            $supplier = SetSupplierAgent::run($supplier, Agent::find(Arr::pull($modelData, 'agent_id')));
        }

        $modelData         = $this->withNameFallingBackToContact($supplier, $modelData);
        $modelData         = $this->pullSupplierJsonColumns($modelData);
        $productsCurrency  = Arr::pull($modelData, 'products_currency');
        $productsCostRate  = null;
        if ($productsCurrency && Arr::has($modelData, 'currency_id') && (int) $modelData['currency_id'] !== $supplier->currency_id) {
            $productsCostRate = $this->getProductsCostRate($supplier, Currency::find($modelData['currency_id']), $productsCurrency === 'convert');
        }

        if (Arr::has($modelData, 'image')) {
            $image = Arr::pull($modelData, 'image');
            if ($image) {
                $supplier = SaveModelImage::run(
                    model: $supplier,
                    imageData: [
                        'path'         => $image->getPathName(),
                        'originalName' => $image->getClientOriginalName(),
                        'extension'    => $image->getClientOriginalExtension(),
                    ],
                    scope: 'photo'
                );
            }
        }

        if (Arr::has($modelData, 'address')) {
            $addressData = Arr::get($modelData, 'address');
            Arr::forget($modelData, 'address');
            UpdateAddress::run($supplier->address, $addressData);
            $supplier->updateQuietly(
                [
                    'location' => $supplier->address->getLocation()
                ]
            );
        }

        $supplier = DB::transaction(function () use ($supplier, $modelData, $productsCostRate) {
            $supplier = $this->update($supplier, $modelData, ['data', 'settings']);
            if ($productsCostRate !== null) {
                $this->moveSupplierProductsToCurrency($supplier, $productsCostRate);
            }

            return $supplier;
        });

        if ($supplier->wasChanged(['email', 'data'])) {
            SyncSupplierPortalContact::run($supplier);
        }

        if ($productsCostRate !== null) {
            $this->hydrateSupplierProductsOrgStockCosts($supplier);
        }

        if ($leavingBillOfLading) {
            $supplier->update(['data' => Arr::except($supplier->data, self::BILL_OF_LADING_FIELDS)]);
        }

        return $supplier;
    }

    /**
     * The name is the nickname; without one it follows the company or contact name.
     */
    private function withNameFallingBackToContact(Supplier $supplier, array $modelData): array
    {
        $nameFromContact = Supplier::nameFromContact(
            Arr::get($modelData, 'company_name', $supplier->company_name),
            Arr::get($modelData, 'contact_name', $supplier->contact_name)
        ) ?: $supplier->name;

        if (Arr::has($modelData, 'name')) {
            $nickname          = trim((string) $modelData['name']);
            $modelData['name'] = $nickname === '' ? $nameFromContact : $nickname;

            return $modelData;
        }

        $contactChanges = Arr::has($modelData, 'company_name') || Arr::has($modelData, 'contact_name');
        if ($contactChanges && $supplier->nickname() === null) {
            $modelData['name'] = $nameFromContact;
        }

        return $modelData;
    }

    private function getProductsCostRate(Supplier $supplier, Currency $newCurrency, bool $convertCosts): float
    {
        if (!$convertCosts) {
            return 1.0;
        }

        $rate = GetCurrencyExchange::run($supplier->currency, $newCurrency);
        if (!$rate) {
            throw ValidationException::withMessages([
                'products_currency' => __('No exchange rate from :from to :to, the costs cannot be converted', ['from' => $supplier->currency->code, 'to' => $newCurrency->code]),
            ]);
        }

        return $rate;
    }

    private function moveSupplierProductsToCurrency(Supplier $supplier, float $rate): void
    {
        foreach ($supplier->supplierProducts as $supplierProduct) {
            $modelData = ['currency_id' => $supplier->currency_id];
            if ($rate !== 1.0) {
                $modelData['cost'] = round($supplierProduct->cost * $rate, 4);
            }
            UpdateSupplierProduct::run($supplierProduct, $modelData);
        }
    }

    private function hydrateSupplierProductsOrgStockCosts(Supplier $supplier): void
    {
        $orgStocks = OrgStock::whereHas('orgSupplierProducts', fn ($query) => $query->whereIn('supplier_product_id', $supplier->supplierProducts()->select('id')))->get();
        foreach ($orgStocks as $orgStock) {
            OrgStockHydrateCurrentSupplierSkuCost::dispatch($orgStock);
        }
    }

    public function rules(): array
    {
        $rules = [
            'code'            => [
                'sometimes',
                'required',
                'max:32',
                'alpha_dash',
                new IUnique(
                    table: 'agents',
                    extraConditions: [
                        ['column' => 'group_id', 'value' => $this->group->id],
                        [
                            'column'   => 'id',
                            'operator' => '!=',
                            'value'    => $this->supplier->id
                        ],
                    ]
                ),
            ],
            'contact_name'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'contact_website' => ['sometimes', 'nullable', 'string', 'max:255'],
            'peppol_id'       => BuildUblInvoice::peppolIdRules(),
            'company_name'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'name'            => ['sometimes', 'nullable', 'string', 'max:255'],
            'email'           => ['sometimes', 'nullable', 'email'],
            'phone'           => ['sometimes', 'nullable', new Phone()],
            'wechat'          => ['sometimes', 'nullable', 'string', 'max:255'],
            'whatsapp'        => ['sometimes', 'nullable', new Phone()],
            'factory_holidays'         => ['sometimes', 'array'],
            'factory_holidays.*.from'  => ['required', 'date'],
            'factory_holidays.*.to'    => ['required', 'date', 'after_or_equal:factory_holidays.*.from'],
            'factory_holidays.*.note'  => ['sometimes', 'nullable', 'string', 'max:255'],
            'address'         => ['sometimes', 'required', new ValidAddress(requireFullAddress: !$this->asAction)],
            'currency_id'     => [
                'sometimes',
                'required',
                'exists:currencies,id',
            ],
            'products_currency' => [
                Rule::requiredIf(fn () => $this->strict
                    && $this->has('currency_id')
                    && (int) $this->get('currency_id') !== $this->supplier->currency_id
                    && $this->supplier->supplierProducts()->exists()),
                'nullable',
                Rule::in(['relabel', 'convert']),
            ],
            'image'           => ['sometimes', 'nullable', File::image()->max(12 * 1024)],
            'agent_id'        => [
                'sometimes',
                'nullable',
                Rule::exists('agents', 'id')->where('group_id', $this->group->id)->whereNull('deleted_at'),
            ],
            'relationship_contact_user_id' => ['sometimes', 'nullable', 'integer', SetSuppliersRelationshipContact::portalContactRule($this->supplier)],
        ];

        $rules = array_merge($rules, $this->supplierJsonFieldRules(), $this->packingRules($this->supplier));
        $rules['extra_emails.*'][] = SyncSupplierPortalContact::notUsedByAnotherSupplierRule($this->supplier);

        if (!$this->strict) {
            $rules['phone']       = ['sometimes', 'nullable', 'max:255'];
            $rules['whatsapp']    = ['sometimes', 'nullable', 'max:255'];
            $rules['archived_at'] = ['sometimes', 'nullable', 'date'];
            $rules                = $this->noStrictUpdateRules($rules);
        }

        return $rules;
    }

    public function asController(Supplier $supplier, ActionRequest $request): Supplier
    {
        $this->supplier = $supplier;
        $this->initialisationFromGroup($supplier->group, $request);

        return $this->handle($supplier, $this->validatedData);
    }

    public function action(Supplier $supplier, array $modelData, int $hydratorsDelay = 0, $strict = true, bool $audit = true): Supplier
    {
        if (!$audit) {
            Supplier::disableAuditing();
        }

        $this->supplier       = $supplier;
        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromGroup($supplier->group, $modelData);

        return $this->handle($supplier, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }

    public function jsonResponse(Supplier $supplier): SupplierResource
    {
        return new SupplierResource($supplier);
    }
}
