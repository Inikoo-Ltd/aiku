<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 01 Oct 2024 10:17:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\CustomerNote;

use App\Actions\OrgAction;
use App\Actions\Traits\WithModelAddressActions;
use App\Models\CRM\Customer;
use App\Models\CRM\CustomerNote;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use OwenIt\Auditing\Resolvers\IpAddressResolver;
use OwenIt\Auditing\Resolvers\UrlResolver;
use OwenIt\Auditing\Resolvers\UserAgentResolver;
use OwenIt\Auditing\Resolvers\UserResolver;

class StoreCustomerNote extends OrgAction
{
    use WithModelAddressActions;
    use WithNotesDetails;


    public function handle(Customer $customer, array $modelData): CustomerNote
    {
        /** @var User $user */
        $user = UserResolver::resolve();

        data_set($modelData, 'group_id', $customer->group_id);
        data_set($modelData, 'organisation_id', $customer->organisation_id);
        data_set($modelData, 'shop_id', $customer->shop_id);
        data_set($modelData, 'customer_id', $customer->id);


        if ($this->strict) {
            data_set($modelData, 'user_type', class_basename($user));
            data_set($modelData, 'user_id', $user->id);
        }

        data_set($modelData, 'auditable_type', 'Customer');
        data_set($modelData, 'auditable_id', $customer->id);


        $modelData = $this->processNotes($modelData);

        data_set($modelData, 'url', UrlResolver::resolve($customer));
        data_set($modelData, 'ip_address', IpAddressResolver::resolve($customer));
        data_set($modelData, 'user_agent', UserAgentResolver::resolve($customer));

        data_set($modelData, 'event', Arr::get($modelData, 'event', 'customer_note'), overwrite: false);
        data_set($modelData, 'tags', Arr::get($modelData, 'tags', ['customer_notes']), overwrite: false);


        /** @var CustomerNote $CustomerNote */
        return $customer->customerNotes()->create($modelData);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("crm.{$this->shop->id}.edit");
    }

    public function rules(): array
    {
        $rules = [
            'note' => ['required', 'string', 'max:1024'],
        ];

        if (!$this->strict) {
            $rules['user_type']         = ['sometimes', Rule::in(['User', 'WebUser'])];
            $rules['user_id']           = ['sometimes', 'required', 'integer'];
            $rules['source_id']         = ['sometimes', 'string'];
            $rules['note']              = ['sometimes', 'string', 'max:4096'];
            $rules['note_details_html'] = ['sometimes', 'string', 'max:4096'];
            $rules['note_details']      = ['sometimes', 'string', 'max:4096'];
            $rules['created_at']        = ['sometimes', 'date'];
            $rules['new_values']        = ['sometimes', 'array'];
            $rules['event']             = ['sometimes', 'string'];
            $rules['tags']              = ['sometimes', 'array'];
        }

        return $rules;
    }


    public function action(Customer $customer, array $modelData, int $hydratorsDelay = 0, bool $strict = true): CustomerNote
    {
        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($customer->shop, $modelData);

        return $this->handle($customer, $this->validatedData);
    }

    public function asController(Customer $customer, ActionRequest $request): CustomerNote
    {
        $this->asAction = true;
        $this->initialisationFromShop($customer->shop, $request);

        return $this->handle($customer, $this->validatedData);
    }


}
