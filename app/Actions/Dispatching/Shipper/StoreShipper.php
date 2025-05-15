<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Feb 2023 16:47:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Shipper;

use App\Actions\Dispatching\Shipper\Hydrators\ShipperHydrateUniversalSearch;
use App\Actions\OrgAction;
use App\Models\Dispatching\Shipper;
use App\Models\SysAdmin\Organisation;
use App\Rules\IUnique;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;

class StoreShipper extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(Organisation $organisation, array $modelData): Shipper
    {
        data_set($modelData, 'group_id', $organisation->group_id);

        if (Arr::exists($modelData, 'base_url')) {
            data_set($modelData, 'settings.base_url', Arr::pull($modelData, 'base_url'));
        }

        $shipper = DB::transaction(function () use ($organisation, $modelData) {
            /** @var Shipper $shipper */
            $shipper = $organisation->shippers()->create($modelData);
            $shipper->stats()->create();

            return $shipper;
        });

        ShipperHydrateUniversalSearch::dispatch($shipper);

        return $shipper;
    }


    public function prepareForValidation(ActionRequest $request): void
    {
        if ($this->has('website') and $this->get('website') != null) {
            if (!Str::startsWith($this->get('website'), 'http')) {
                $this->fill(['website' => 'https://'.$this->get('website')]);
            }
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
                        ['column' => 'group_id', 'value' => $this->organisation->group_id],
                    ]
                ),
            ],
            'name'         => ['required', 'max:255', 'string'],
            'api_shipper'  => ['sometimes', 'nullable', 'string', 'max:255'],
            'contact_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'company_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email'        => ['sometimes', 'nullable', 'email'],
            'phone'        => ['sometimes', 'nullable', 'string', 'max:255'],
            'website'      => ['sometimes', 'nullable', 'url'],
            'tracking_url' => ['sometimes', 'nullable', 'string', 'max:255'],
            'source_id'    => ['sometimes', 'nullable', 'string', 'max:64'],
            'base_url'     => ['sometimes'],
        ];

        if (!$this->strict) {
            $rules['created_at'] = ['sometimes', 'date'];
            $rules['fetched_at'] = ['sometimes', 'date'];
            $rules['deleted_at'] = ['sometimes', 'nullable', 'date'];
            $rules['source_id']  = ['sometimes', 'string', 'max:255'];
        }

        return $rules;
    }

    /**
     * @throws \Throwable
     */
    public function action(Organisation $organisation, array $modelData, int $hydratorsDelay = 0, bool $strict = true, $audit = true): Shipper
    {
        if (!$audit) {
            Shipper::disableAuditing();
        }
        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisation($organisation, $modelData);

        return $this->handle($organisation, $this->validatedData);
    }

    public function asController(Organisation $organisation, ActionRequest $request): Shipper
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation, $this->validatedData);
    }
}
