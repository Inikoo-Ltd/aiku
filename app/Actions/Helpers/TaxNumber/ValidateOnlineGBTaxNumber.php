<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 27 Oct 2025 22:15:06 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\TaxNumber;

use App\Actions\Helpers\TaxNumber\Concerns\AsTaxNumberCommand;
use App\Models\Helpers\TaxNumber;
use Exception;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\Concerns\AsAction;

class ValidateOnlineGBTaxNumber
{
    use AsAction;
    use AsTaxNumberCommand;


    public function handle(TaxNumber $taxNumber): TaxNumber
    {
        if (!config('hmrc_api.enabled')) {
            return $taxNumber;
        }

        $taxNumber = ValidateGBTaxNumber::make()->cleanTaxNumber($taxNumber->number);


        $resultApiCall = $this->checkVatNumber($taxNumber);


        return $taxNumber;
    }

    /**
     * @throws \Illuminate\Http\Client\ConnectionException
     * @throws \Exception
     */
    private function getAccessToken()
    {
        $response = Http::asForm()->post(config('hmrc_api.base_url').'/oauth/token', [
            'grant_type'    => 'client_credentials',
            'client_id'     => config('hmrc_api.client_id'),
            'client_secret' => config('hmrc_api.client_secret'),
            'scope'         => 'read:vat',
        ]);

        if ($response->successful()) {
            return $response->json()['access_token'];
        }
        throw new Exception('Error obtaining access token: '.$response->body());
    }

    public function checkVatNumber($vatNumber)
    {
        $accessToken = $this->getAccessToken();

        $response = Http::withToken($accessToken)
            ->accept('application/vnd.hmrc.2.0+json')
            ->get(config('hmrc_api.base_url')."/organisations/vat/check-vat-number/lookup/$vatNumber");
        if ($response->successful()) {
            return $response->json();
        }
        throw new \Exception('Error checking VAT number: '.$response->body());
    }


    public function getCommandSignature(): string
    {
        return 'validate:online_tax_number_gb {id}';
    }


}
