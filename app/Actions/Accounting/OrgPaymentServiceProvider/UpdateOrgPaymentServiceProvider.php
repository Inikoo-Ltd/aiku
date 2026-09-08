<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 09 Apr 2024 22:11:19 Central Indonesia Time, Bali Office , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\OrgPaymentServiceProvider;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Http\Resources\Accounting\OrgPaymentServiceProviderResource;
use App\Models\Accounting\OrgPaymentServiceProvider;
use App\Rules\IUnique;
use Lorisleiva\Actions\ActionRequest;

class UpdateOrgPaymentServiceProvider extends OrgAction
{
    use WithActionUpdate;

    private orgPaymentServiceProvider $orgPaymentServiceProvider;

    public function handle(OrgPaymentServiceProvider $orgPaymentServiceProvider, array $modelData): OrgPaymentServiceProvider
    {
        return $this->update($orgPaymentServiceProvider, $modelData, ['data']);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("sysadmin.edit");
    }

    public function rules(): array
    {
        $rules = [
            'code' => [
                'sometimes',
                'required',
                'between:2,16',
                'alpha_dash',
                new IUnique(
                    table: 'payment_service_providers',
                    extraConditions: [
                        ['column' => 'group_id', 'value' => $this->group->id],
                        [
                            'column'   => 'id',
                            'operator' => '!=',
                            'value'    => $this->orgPaymentServiceProvider->id
                        ],
                    ]
                ),
            ],
            'name'      => [
                'sometimes',
                'required',
                'max:255',
                'string',
            ],
        ];

        if (!$this->strict) {
            $rules['last_fetched_at'] = ['sometimes', 'date'];
        }
        return $rules;
    }

    public function asController(OrgPaymentServiceProvider $orgPaymentServiceProvider, ActionRequest $request): OrgPaymentServiceProvider
    {
        $this->orgPaymentServiceProvider = $orgPaymentServiceProvider;
        $this->initialisationFromGroup($orgPaymentServiceProvider->group, $request);

        return $this->handle($orgPaymentServiceProvider, $this->validatedData);
    }

    public function action(OrgPaymentServiceProvider $orgPaymentServiceProvider, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): OrgPaymentServiceProvider
    {
        $this->strict = $strict;
        if (!$audit) {
            OrgPaymentServiceProvider::disableAuditing();
        }
        $this->asAction                  = true;
        $this->hydratorsDelay = $hydratorsDelay;

        $this->orgPaymentServiceProvider = $orgPaymentServiceProvider;
        $this->initialisationFromGroup($orgPaymentServiceProvider->group, $modelData);

        return $this->handle($orgPaymentServiceProvider, $this->validatedData);
    }

    public function jsonResponse(OrgPaymentServiceProvider $orgPaymentServiceProvider): OrgPaymentServiceProviderResource
    {
        return new OrgPaymentServiceProviderResource($orgPaymentServiceProvider);
    }
}
