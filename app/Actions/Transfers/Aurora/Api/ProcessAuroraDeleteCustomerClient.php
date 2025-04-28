<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 13 Mar 2025 23:42:13 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora\Api;

use App\Actions\Dropshipping\CustomerClient\ForceDeleteCustomerClient;
use App\Actions\OrgAction;
use App\Models\Dropshipping\CustomerClient;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;

/**
 * @property string $fetcher
 */
class ProcessAuroraDeleteCustomerClient extends OrgAction
{
    use WithProcessAurora;

    public function rules(): array
    {
        return [
            'id'              => ['required', 'integer'],
            'bg'              => ['sometimes', 'boolean'],
            'delay'           => ['sometimes', 'integer']
        ];
    }

    /**
     * @throws \Throwable
     */
    public function handle(Organisation $organisation, array $modelData): array
    {
        $res = [
            'status'  => 'error',
            'message' => 'Customer client not found',
            'model'   => 'DeleteCustomerClient'
        ];


        $customerClient = CustomerClient::where('source_id', $organisation->id.':'.$modelData['id'])->first();

        if ($customerClient) {

            ForceDeleteCustomerClient::make()->action($customerClient);

            $res = [
                'status' => 'ok',
                'id'     => $customerClient->source_id,
                'model'  => 'DeleteCustomerClient',
            ];
        }

        return $res;
    }

    /**
     * @throws \Throwable
     */
    public function action(Organisation $organisation, array $modelData): array
    {
        $this->initialisation($organisation, $modelData);
        return $this->handle($organisation, $this->validatedData);
    }


    /**
     * @throws \Throwable
     */
    public function asController(Organisation $organisation, ActionRequest $request): array
    {
        $this->initialisation($organisation, $request);
        return $this->handle($organisation, $this->validatedData);
    }

}
