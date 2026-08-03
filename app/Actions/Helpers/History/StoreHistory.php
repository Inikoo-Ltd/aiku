<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 04 Oct 2024 11:58:26 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\History;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Helpers\History;
use App\Models\SysAdmin\Organisation;
use Illuminate\Validation\Rule;
use OwenIt\Auditing\Resolvers\IpAddressResolver;
use OwenIt\Auditing\Resolvers\UrlResolver;
use OwenIt\Auditing\Resolvers\UserAgentResolver;

class StoreHistory extends OrgAction
{
    public function handle($auditable, array $modelData): History
    {
        data_set($modelData, 'group_id', $auditable->group_id);
        data_set($modelData, 'organisation_id', $auditable instanceof Organisation ? $auditable->id : $auditable->organisation_id);
        data_set($modelData, 'shop_id', $auditable instanceof Shop ? $auditable->id : $auditable->shop_id);
        data_set($modelData, 'customer_id', $auditable instanceof Customer ? $auditable->id : $auditable->customer_id);
        data_set($modelData, 'auditable_type', class_basename($auditable));
        data_set($modelData, 'auditable_id', $auditable->id);
        data_set($modelData, 'url', UrlResolver::resolve($auditable));
        data_set($modelData, 'ip_address', IpAddressResolver::resolve($auditable));
        data_set($modelData, 'user_agent', UserAgentResolver::resolve($auditable));

        /** @var History $history */
        $history = History::create($modelData);

        return $history;
    }


    public function rules(): array
    {
        return [
            'new_values' => ['sometimes', 'array'],
            'old_values' => ['sometimes', 'array'],
            'data'       => ['sometimes', 'array'],
            'user_type'  => ['sometimes', Rule::in(['User', 'WebUser'])],
            'user_id'    => ['sometimes', 'required', 'integer'],
            'source_id'  => ['sometimes', 'string'],
            'created_at' => ['required', 'date'],
            'fetched_at' => ['required', 'date'],
            'event'      => ['required', 'string'],
            'tags'       => ['required', 'array']

        ];
    }


    public function action($auditable, array $modelData, int $hydratorsDelay = 0): History
    {
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromGroup($auditable->group, $modelData);

        return $this->handle($auditable, $this->validatedData);
    }


}
