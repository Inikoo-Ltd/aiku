<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Sept 2025 15:34:22 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina;

use App\Actions\Dispatching\Picking\WithAuroraApi;
use App\Actions\IrisAction;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\ActionRequest;

class UnsubscribeAurora extends IrisAction
{
    use WithAuroraApi;


    /**
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function handle(array $modelData): array
    {
        $apiUrl = $this->getApiUrl($this->organisation);


        $websiteSource = explode(':', $this->website->source_id);


        $response = Http::withHeaders([
            'secret' => $this->getApiToken($this->organisation),
        ])->withQueryParameters(
            [

                'action'      => 'unsubscribe',
                's'           => Arr::get($modelData, 's'),
                'a'           => Arr::get($modelData, 'a'),
                'website_key' => $websiteSource[1],
                'picker_name' => 'customer'

            ]
        )->get($apiUrl);

        $data = $response->json() ?? $response->body();

        return [
            'api_response_status' => $response->status(),
            'api_response_data'   => $data,
        ];
    }

    public function rules(): array
    {
        return [
            's' => ['required', 'string'],
            'a' => ['required', 'string'],
        ];
    }


    /**
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function asController(ActionRequest $request): array
    {
        $this->initialisation($request);

        return $this->handle($this->validatedData);
    }

}
