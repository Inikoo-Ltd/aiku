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
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use phpDocumentor\Reflection\Exception;
use SoapClient;
use SoapFault;

class ValidateEuropeanTaxNumber
{
    use AsAction;

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
            try {
                $number  = preg_replace('/\s+/', '', (string)$taxNumber->number);
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
                    $name    = trim(preg_replace('/\s+/', ' ', (string)$response->name));
                    $address = trim(preg_replace('/\s+/', ' ', (string)$response->address));

                    $validationData['data'] = [
                        'name'               => $name,
                        'address'            => $address,
                    ];
                }


                $taxNumber->update($validationData);
            } catch (SoapFault $e) {
                $validationDate = gmdate('Y-m-d H:i:s');


                if (!preg_match('/INVALID_INPUT/i', $e->getMessage())) {
                    $validationData = [
                        'valid'              => false,
                        'status'             => TaxNumberStatusEnum::INVALID,
                        'checked_at'         => $validationDate,
                        'invalid_checked_at' => $validationDate

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

                throw new Exception($e->getMessage(), $e->getCode());
            }
        }


        return $taxNumber;
    }

    public function getCommandSignature(): string
    {
        return 'validate:tax_number {id}';
    }

    /**
     * @throws \phpDocumentor\Reflection\Exception
     */
    public function asCommand(Command $command): int
    {
        $taxNumber = TaxNumber::findOrFail($command->argument('id'));
        $taxNumber = $this->handle($taxNumber);


        $fields = [
            'id'                         => $taxNumber->id,
            'type'                       => $taxNumber->type->value,
            'country_code'               => $taxNumber->country_code,
            'number'                     => $taxNumber->number,
            'valid'                      => $taxNumber->valid ? 'true' : 'false',
            'status'                     => $taxNumber->status->value,
            'checked_at'                 => $taxNumber->checked_at,
            'invalid_checked_at'         => $taxNumber->invalid_checked_at,
            'external_service_failed_at' => $taxNumber->external_service_failed_at,
        ];

        foreach ($fields as $key => $value) {
            $command->line(str_pad($key, 28).': '.($value ?? ''));
        }

        if (!empty($taxNumber->data)) {
            $command->line('data:');
            $command->line(json_encode($taxNumber->data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }

        return 0;
    }

}
