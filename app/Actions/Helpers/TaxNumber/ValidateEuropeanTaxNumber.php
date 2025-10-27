<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 26 Mar 2023 01:55:15 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\TaxNumber;

use App\Enums\Helpers\TaxNumber\TaxNumberStatusEnum;
use App\Enums\Helpers\TaxNumber\TaxNumberTypeEnum;
use App\Models\Helpers\TaxNumber;
use App\Actions\Helpers\TaxNumber\Concerns\AsTaxNumberCommand;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use SoapClient;
use SoapFault;

class ValidateEuropeanTaxNumber
{
    use AsAction;
    use AsTaxNumberCommand;

    public function __construct(int $timeout = 10)
    {
        $this->timeout = $timeout;
    }

    public const string URL = 'https://ec.europa.eu/taxation_customs/vies/checkVatService.wsdl';

    private ?SoapClient $client = null;

    protected int $timeout;

    /**
     * @throws \SoapFault
     */
    protected function getClient(): SoapClient
    {
        if ($this->client === null) {
            $this->client = new SoapClient(self::URL, ['connection_timeout' => $this->timeout]);
        }

        return $this->client;
    }

    /**
     * @throws \phpDocumentor\Reflection\Exception
     */
    public function handle(TaxNumber $taxNumber): TaxNumber
    {
        if ($taxNumber->type == TaxNumberTypeEnum::EU_VAT) {
            if (!$taxNumber->number || strlen($taxNumber->number) < 7) {
                $validationData = [
                    'valid'              => false,
                    'status'             => TaxNumberStatusEnum::INVALID,
                    'checked_at'         => now(),
                    'invalid_checked_at' => now()

                ];
                $taxNumber->update($validationData);
                $taxNumber->refresh();

                return $taxNumber;
            }


            try {
                $number  = preg_replace('/\s+/', '', $taxNumber->number);
                $country = strtoupper((string)$taxNumber->country_code);
                if (strlen($number) >= 2 && strtoupper(substr($number, 0, 2)) === $country) {
                    $number = substr($number, 2);
                }


                $response = $this->getClient()->checkVat(
                    array(
                        'countryCode' => $taxNumber->country_code,
                        'vatNumber'   => $number
                    )
                );



                $validationDate = now();
                $validationData = [
                    'valid'      => $response->valid,
                    'status'     => $response->valid ? TaxNumberStatusEnum::VALID : TaxNumberStatusEnum::INVALID,
                    'checked_at' => $validationDate
                ];
                if (!$response->valid) {
                    $validationData['invalid_checked_at'] = $validationDate;
                } else {
                    $validationData['invalid_checked_at'] = null;
                    $name                                 = trim(preg_replace('/\s+/', ' ', (string)$response->name));
                    $address                              = trim(preg_replace('/\s+/', ' ', (string)$response->address));

                    $validationData['data'] = [
                        'name'    => $name,
                        'address' => $address,
                    ];
                }


                $taxNumber->update($validationData);
            } catch (SoapFault $e) {
                if (!preg_match('/INVALID_INPUT/i', $e->getMessage())) {
                    $validationData = [
                        'valid'              => false,
                        'status'             => TaxNumberStatusEnum::INVALID,
                        'checked_at'         => now(),
                        'invalid_checked_at' => now()

                    ];
                } else {
                    $validationData = [
                        'external_service_failed_at' => gmdate('Y-m-d H:i:s'),
                        'data'                       => [
                            'exception' => [
                                'code'    => $e->getCode(),
                                'message' => Str::limit($e->getMessage(), 4000)
                            ]
                        ]
                    ];
                }

                $taxNumber->update($validationData);
                $taxNumber->refresh();
            }
        }


        return $taxNumber;
    }

    public function getCommandSignature(): string
    {
        return 'validate:tax_number {id}';
    }


}
