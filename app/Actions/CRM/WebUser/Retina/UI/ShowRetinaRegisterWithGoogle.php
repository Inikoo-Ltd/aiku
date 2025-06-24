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
use Google\Service\Oauth2;
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
            'google_access_token' => ['required', 'string'],
        ];
    }

    public function prepareForValidation(ActionRequest $request)
    {
        $this->set('google_access_token', $request->input('google_access_token'));
    }

    public function afterValidator(Validator $validator)
    {
        $googleAccessToken = $this->get('google_access_token');
        try {
            $client = new Google_Client();
            $client->setClientId(config('services.google.client_id'));

            $client->setAccessToken(['access_token' => $googleAccessToken]);

            $oauth2 = new Oauth2($client);

            $googleUser = $oauth2->userinfo->get();

            $payload =  [
                'id'    => $googleUser->getId(),
                'email' => $googleUser->getEmail(),
                'name'  => $googleUser->getName(),
            ];
            $this->set('googleData', $payload);
        } catch (\Exception $e) {
            $validator->errors()->add('google_access_token', __('The provided Google credential is invalid: :message', ['message' => $e->getMessage()]));
        }
    }


    public function asController(ActionRequest $request): Response
    {
        $this->initialisation($request);


        return $this->handle($request);
    }

}
