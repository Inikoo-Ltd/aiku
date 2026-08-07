<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 03 Apr 2024 20:49:22 Central Indonesia Time, Bali Office, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\Supplier;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithSupplyChainEditAuthorisation;
use App\Actions\Helpers\Address\UpdateAddress;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Http\Resources\SupplyChain\SupplierResource;
use App\Models\SupplyChain\Supplier;
use App\Rules\IUnique;
use App\Rules\Phone;
use App\Rules\ValidAddress;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class UpdateSupplier extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithSupplierJsonColumns;
    use WithSupplyChainEditAuthorisation;

    private Supplier $supplier;
    private bool $action = false;

    public function handle(Supplier $supplier, array $modelData): Supplier
    {
        $leavingContainer = Arr::exists($modelData, 'delivery_type')
            && Arr::get($modelData, 'delivery_type') !== 'container';

        if ($leavingContainer) {
            Arr::forget($modelData, self::CONTAINER_ONLY_FIELDS);
        }

        $modelData = $this->pullSupplierJsonColumns($modelData);

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

        $supplier = $this->update($supplier, $modelData, ['data', 'settings']);

        if ($leavingContainer) {
            $supplier->update(['data' => Arr::except($supplier->data, self::CONTAINER_ONLY_FIELDS)]);
        }

        if ($supplier->wasChanged(['name', 'code'])) {
            foreach ($supplier->orgSuppliers as $orgSupplier) {
                $orgSupplier->update(
                    [
                        'code' => $supplier->code,
                        'name' => $supplier->name
                    ]
                );
            }
        }

        return $supplier;
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
            'company_name'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'email'           => ['sometimes', 'nullable', 'email'],
            'phone'           => ['sometimes', 'nullable', new Phone()],
            'address'         => ['sometimes', 'required', new ValidAddress()],
            'currency_id'     => ['sometimes', 'required', 'exists:currencies,id'],
        ];

        $rules = array_merge($rules, $this->supplierJsonFieldRules());

        if (!$this->strict) {
            $rules['phone']       = ['sometimes', 'nullable', 'max:255'];
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

    public function jsonResponse(Supplier $supplier): SupplierResource
    {
        return new SupplierResource($supplier);
    }
}
