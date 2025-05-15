<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Feb 2023 16:47:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Shipper;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dispatching\Shipper;
use App\Models\SysAdmin\Organisation;
use App\Rules\IUnique;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;

class UpdateShipper extends OrgAction
{
    use WithActionUpdate;


    private Shipper $shipper;

    public function handle(Shipper $shipper, array $modelData): Shipper
    {
        if (Arr::exists($modelData, 'base_url')) {
            data_set($modelData, 'settings.base_url', Arr::pull($modelData, 'base_url'));
        }

        return $this->update($shipper, $modelData, ['data', 'settings']);
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        if ($this->has('website') and $this->get('website') != null) {
            if (!Str::startsWith($this->get('website'), 'http')) {
                $this->fill(['website' => 'https://'.$this->get('website')]);
            }
        }
        if (!$this->has('code') and $this->get('code') == null) {
            $this->set('code', $this->shipper->code);
        }
    }


    public function rules(): array
    {
        $rules = [
            'code'         => [
                'required',
                'between:2,16',
                'alpha_dash',
                new IUnique(
                    table: 'shippers',
                    extraConditions: [
                        [
                            'column' => 'group_id',
                            'value'  => $this->organisation->group_id
                        ],
                        [
                            'column'   => 'id',
                            'operator' => '!=',
                            'value'    => $this->shipper->id
                        ],
                    ]
                ),
            ],
            'name'         => ['sometimes', 'required', 'max:255', 'string'],
            'api_shipper'  => ['sometimes', 'nullable', 'string', 'max:255'],
            'contact_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'company_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email'        => ['sometimes', 'nullable', 'email'],
            'phone'        => ['sometimes', 'nullable', 'string', 'max:255'],
            'website'      => ['sometimes', 'nullable', 'url'],
            'tracking_url' => ['sometimes', 'nullable', 'string', 'max:255'],
            'base_url'     => ['sometimes'],
        ];

        if (!$this->strict) {
            $rules['last_fetched_at'] = ['sometimes', 'date'];
        }

        return $rules;
    }


    public function action(Shipper $shipper, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): Shipper
    {
        $this->strict = $strict;
        if (!$audit) {
            Shipper::disableAuditing();
        }
        $this->asAction = true;
        $this->shipper  = $shipper;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisation($shipper->organisation, $modelData);

        return $this->handle($shipper, $this->validatedData);
    }

    public function asController(Organisation $organisation, Shipper $shipper, ActionRequest $request): Shipper
    {

        $this->shipper = $shipper;
        $this->initialisation($organisation, $request);

        return $this->handle($shipper, $this->validatedData);
    }
}
