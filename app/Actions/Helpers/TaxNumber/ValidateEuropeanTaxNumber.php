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
use App\Models\SysAdmin\User;
use App\Actions\Helpers\TaxNumber\Concerns\AsTaxNumberCommand;
use App\Actions\Helpers\TaxNumber\Traits\WithValidateTaxNumberCustomAudit;
use App\Enums\Helpers\TaxNumber\TaxNumberValidationTypeEnum;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use SoapClient;
use SoapFault;

class ValidateEuropeanTaxNumber
{
    use AsAction;
    use AsTaxNumberCommand;
    use WithValidateTaxNumberCustomAudit;

    public function __construct(int $timeout = 10)
    {
        $this->timeout = $timeout;
    }

    public const array RECHECK_DELAYS_IN_HOURS = [2, 24, 48];

    public const int MAX_CHECKS_PER_HOUR = 20;

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
    public function handle(TaxNumber $taxNumber, TaxNumber|null $oldTaxNumberData = null, bool $scheduleRecheck = true): TaxNumber
    {
        if (!$oldTaxNumberData) {
            $oldTaxNumberData = $taxNumber->replicate();
        }

        if ($taxNumber->type == TaxNumberTypeEnum::EU_VAT) {
            if (!$scheduleRecheck && $taxNumber->valid) {
                return $taxNumber;
            }

            if (!$taxNumber->number || strlen($taxNumber->number) < 7) {
                $validationData = [
                    'valid'              => false,
                    'status'             => TaxNumberStatusEnum::INVALID,
                    'checked_at'         => now(),
                    'invalid_checked_at' => now()

                ];
                $taxNumber->update($validationData);
                $taxNumber->refresh();

                $this->deployTaxValidationCustomAudit($oldTaxNumberData, $taxNumber, TaxNumberValidationTypeEnum::BASIC);

                return $taxNumber;
            }

            if ($this->isCustomerDriven() && !RateLimiter::attempt($this->rateLimiterKey($taxNumber), self::MAX_CHECKS_PER_HOUR, fn () => true)) {
                self::dispatch($taxNumber, null, $scheduleRecheck)
                    ->onQueue('low-priority')
                    ->delay(now()->addSeconds(RateLimiter::availableIn($this->rateLimiterKey($taxNumber)) + 60));

                return $taxNumber;
            }

            try {
                $number      = preg_replace('/\s+/', '', $taxNumber->number);
                $countryCode = strtoupper((string)$taxNumber->country_code);
                if (strlen($number) >= 2 && strtoupper(substr($number, 0, 2)) === $countryCode) {
                    $number = substr($number, 2);
                }
                if ($countryCode === 'GR' && strtoupper(substr($number, 0, 2)) === 'EL') {
                    $number = substr($number, 2);
                }


                $response = $this->getClient()->checkVat(
                    array(
                        'countryCode' => $taxNumber->country_code == 'GR' ? 'EL' : $taxNumber->country_code,
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
                $taxNumber->refresh();

                $this->deployTaxValidationCustomAudit($oldTaxNumberData, $taxNumber, TaxNumberValidationTypeEnum::ONLINE, "Checked through VIES");
            } catch (SoapFault $e) {
                if ($this->isMalformedNumberFault($e->getMessage())) {
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

                $thirdPartyStatus = isset($validationData['external_service_failed_at']) ? 'Checked through VIES. VIES is down' : 'Checked through VIES';

                $this->deployTaxValidationCustomAudit($oldTaxNumberData, $taxNumber, TaxNumberValidationTypeEnum::ONLINE, $thirdPartyStatus);
            }

            if ($scheduleRecheck) {
                $this->scheduleRechecks($taxNumber);
            }
        }


        return $taxNumber;
    }

    /**
     * Only customers editing their own number are throttled. Staff in the back office are
     * trusted, a customer service agent fixing several accounts in a row must never be slowed
     * down, and so are the console and the queue. Anyone else, signed in on the storefront or
     * registering as a guest, is a customer.
     */
    public function isCustomerDriven(): bool
    {
        return !app()->runningInConsole() && !(auth()->user() instanceof User);
    }

    /**
     * Keyed on the owner, not the tax number: clearing the number deletes the row and typing
     * one again creates a new one, so a per-row key would reset on every retype. Typos happen
     * and a customer may need several goes, so the allowance is deliberately loose. Past it
     * the check is only deferred, never dropped.
     */
    public function rateLimiterKey(TaxNumber $taxNumber): string
    {
        return 'validate-eu-tax-number:'.$taxNumber->owner_type.':'.$taxNumber->owner_id;
    }

    /**
     * A registration can be too new for VIES, or VIES momentarily out of sync. Re-check a few
     * times; each re-check is equivalent to a person editing the number again by hand, the
     * validity cascade in the model does the rest (HELP-2374). One round of re-checks per
     * tax number per 48 hours, however many times the number is edited.
     */
    public function scheduleRechecks(TaxNumber $taxNumber): void
    {
        if ($taxNumber->valid || !$this->isPlausibleEuropeanTaxNumber($taxNumber)) {
            return;
        }

        $lastRoundStartedAt = $taxNumber->rechecks_scheduled_at;
        if ($lastRoundStartedAt && $lastRoundStartedAt->greaterThan(now()->subHours(max(self::RECHECK_DELAYS_IN_HOURS)))) {
            return;
        }

        $taxNumber->updateQuietly(['rechecks_scheduled_at' => now()]);

        foreach (self::RECHECK_DELAYS_IN_HOURS as $hours) {
            self::dispatch($taxNumber, null, false)->onQueue('low-priority')->delay(now()->addHours($hours));
        }
    }

    /**
     * Only INVALID_INPUT says anything about the number itself. Every other VIES fault
     * (MS_MAX_CONCURRENT_REQ, MS_UNAVAILABLE, SERVICE_UNAVAILABLE, TIMEOUT) is the service
     * failing us and must never stamp a good number as invalid.
     */
    public function isMalformedNumberFault(string $message): bool
    {
        return (bool)preg_match('/INVALID_INPUT/i', $message);
    }

    public function isPlausibleEuropeanTaxNumber(TaxNumber $taxNumber): bool
    {
        if ($taxNumber->type != TaxNumberTypeEnum::EU_VAT) {
            return false;
        }

        $number = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)$taxNumber->number));
        $prefix = strtoupper((string)$taxNumber->country_code) == 'GR' ? 'EL' : strtoupper((string)$taxNumber->country_code);

        if (str_starts_with($number, $prefix)) {
            $number = substr($number, strlen($prefix));
        } elseif (preg_match('/^[A-Z]{2}/', $number)) {
            return false;
        }

        return strlen($number) >= 8 && strlen($number) <= 12 && preg_match('/\d/', $number) === 1;
    }

    public function getCommandSignature(): string
    {
        return 'validate:tax_number {id}';
    }


}
