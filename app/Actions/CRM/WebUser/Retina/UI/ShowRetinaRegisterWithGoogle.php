<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 Feb 2024 17:08:30 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\WebUser\Retina\UI;

use App\Actions\IrisAction;
use Google_Client;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;
use App\Actions\Helpers\Country\UI\GetAddressData;
use App\Http\Resources\CRM\PollsResource;
use App\Models\CRM\Poll;
use Illuminate\Validation\Validator;

class ShowRetinaRegisterWithGoogle extends IrisAction
{
    use AsController;


    public function handle(ActionRequest $request): Response
    {
        $shop          = $this->shop;
        $polls         = Poll::where('shop_id', $shop->id)->where('in_registration', true)->where('in_iris', true)->get();
        $pollsResource = PollsResource::collection($polls)->toArray($request);


        return Inertia::render(
            'Auth/RegistrationWithGoogle',
            [
                'countriesAddressData' => GetAddressData::run(),
                'polls'                => $pollsResource,
                'registerRoute'        => [
                    'name'       => 'retina.register_from_google.store',
                ],

                'googleData' => $this->get('googleData'),

                'google'               => [
                    'client_id' => config('services.google.client_id')
                ]
            ]
        );
    }

    public function rules(): array
    {
        return [
            'google_credential' => ['required', 'string'],
        ];
    }

    public function prepareForValidation(ActionRequest $request)
    {
        $this->set('google_credential', $request->input('google_credential'));
    }

    public function afterValidator(Validator $validator)
    {
        $credential = $this->get('google_credential');
        $client  = new Google_Client(['client_id' => config('services.google.client_id')]);
        $payload = $client->verifyIdToken($credential);
        if (!$payload) {
            $validator->errors()->add('google_credential', __('The provided Google credential is invalid.'));
        } else {
            $this->set('googleData', $payload);
        }
    }


    public function asController(ActionRequest $request): Response
    {
        $this->initialisation($request);


        return $this->handle($request);
    }

}
