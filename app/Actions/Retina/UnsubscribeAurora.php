<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Sept 2025 15:34:22 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina;

use App\Actions\CRM\CustomerComms\UpdateCustomerComms;
use App\Actions\CRM\Prospect\UpdateProspect;
use App\Actions\Dispatching\Picking\WithAuroraApi;
use App\Actions\IrisAction;
use App\Enums\CRM\Prospect\ProspectContactedStateEnum;
use App\Enums\CRM\Prospect\ProspectFailStatusEnum;
use App\Enums\CRM\Prospect\ProspectSuccessStatusEnum;
use App\Models\CRM\Customer;
use App\Models\CRM\Prospect;
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
        $apiUrl         = $this->getApiUrl($this->organisation);
        $auroraApiToken = $this->getApiToken($this->organisation);
        if (!$auroraApiToken || !app()->environment('production')) {
            return [
                'api_response_status' => 200,
                'api_response_data'   => []
            ];
        }

        $websiteSource = explode(':', $this->website->source_id);


        $response = Http::withHeaders([
            'secret' => $auroraApiToken,
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

        $type = Arr::get($data, 'unsubscribe_subject_type');
        if ($type == 'Customer') {
            $source_id = $this->organisation.':'.Arr::get($data, 'unsubscribe_subject_key');
            $customer  = Customer::where('source_id', $source_id)->first();
            if (!$customer) {
                $customer = Customer::where('post_source_id', $source_id)->first();
            }

            if ($customer) {
                UpdateCustomerComms::run(
                    $customer->comms,
                    [
                        'is_subscribed_to_newsletter'       => false,
                        'is_subscribed_to_marketing'        => false,
                        'is_subscribed_to_abandoned_cart'   => false,
                        'is_subscribed_to_reorder_reminder' => false,
                        'is_subscribed_to_basket_low_stock' => false,
                        'is_subscribed_to_basket_reminder'  => false,
                    ],
                    false
                );
            }
        } elseif ($type == 'Prospect') {
            $source_id = $this->organisation.':'.Arr::get($data, 'unsubscribe_subject_key');
            $prospect  = Prospect::where('source_id', $source_id)->first();
            if (!$prospect) {
                $prospect = Prospect::where('post_source_id', $source_id)->first();
            }

            UpdateProspect::make()->run(
                $prospect,
                [
                    'dont_contact_me'        => true,
                    'can_contact_by_email'   => false,
                    'can_contact_by_phone'   => false,
                    'can_contact_by_address' => false,
                    'fail_status'            => ProspectFailStatusEnum::UNSUBSCRIBED,
                    'success_status'         => ProspectSuccessStatusEnum::NA,
                    'contacted_state'        => ProspectContactedStateEnum::OPEN,
                ],
                false,
            );
        }


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
