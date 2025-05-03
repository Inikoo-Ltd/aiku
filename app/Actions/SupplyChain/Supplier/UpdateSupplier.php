<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 03 Apr 2024 20:49:22 Central Indonesia Time, Bali Office, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\Supplier;

use App\Actions\GrpAction;
use App\Actions\Helpers\Address\UpdateAddress;
use App\Actions\SupplyChain\Supplier\Search\SupplierRecordSearch;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Http\Resources\SupplyChain\SupplierResource;
use App\Models\SupplyChain\Supplier;
use App\Rules\IUnique;
use App\Rules\Phone;
use App\Rules\ValidAddress;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class UpdateSupplier extends GrpAction
{
    use WithActionUpdate;
    use WithNoStrictRules;

    private Supplier $supplier;
    private bool $action = false;

    public function handle(Supplier $supplier, array $modelData): Supplier
    {
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

        SupplierRecordSearch::dispatch($supplier);

        return $supplier;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->action = true) {
            return true;
        }

        return $request->user()->authTo("procurement.".$this->group->id.".edit");
    }

    public function rules(): array
    {
        $rules = [
            'code'         => [
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
            'contact_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'contact_website' => ['sometimes', 'nullable', 'string', 'max:255'],
            'company_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email'        => ['sometimes', 'nullable', 'email'],
            'phone'        => ['sometimes', 'nullable', new Phone()],
            'address'      => ['sometimes', 'required', new ValidAddress()],
            'currency_id'  => ['sometimes', 'required', 'exists:currencies,id'],
        ];

        if (!$this->strict) {
            $rules['phone']       = ['sometimes', 'nullable', 'max:255'];
            $rules['archived_at'] = ['sometimes', 'nullable', 'date'];
            $rules                = $this->noStrictUpdateRules($rules);
        }

        return $rules;
    }

    public function action(Supplier $supplier, array $modelData, int $hydratorsDelay = 0, $strict = true, bool $audit = true): Supplier
    {
        if (!$audit) {
            Supplier::disableAuditing();
        }
        $this->supplier       = $supplier;
        $this->action         = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisation($supplier->group, $modelData);

        return $this->handle($supplier, $this->validatedData);
    }

    public function asController(Supplier $supplier, ActionRequest $request): Supplier
    {
        $this->supplier = $supplier;
        $this->initialisation($supplier->group, $request);

        return $this->handle($supplier, $this->validatedData);
    }


    public function jsonResponse(Supplier $supplier): SupplierResource
    {
        return new SupplierResource($supplier);
    }
}
