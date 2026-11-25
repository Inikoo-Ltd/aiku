<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\EInvoice;

use App\Actions\Retina\SysAdmin\UpdateRetinaCustomer;
use App\Models\CRM\Customer;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

/**
 * Checks a Slovak business customer's DIČ against the Finančná správa register of income tax subjects (ds_dsrdp), looked
 * up by the customer's IČO, and keeps the result in data.dic_verification. A customer without a DIČ gets the registered
 * one only on its first check and when the registered name is the customer's name, so a DIČ staff cleared stays empty;
 * anything else is left for staff to decide.
 */
class VerifyCustomerDic
{
    use AsAction;

    public const string MATCHED = 'matched';
    public const string MISMATCH = 'mismatch';
    public const string FILLED = 'filled';
    public const string NAME_DIFFERS = 'name_differs';
    public const string NOT_REFILLED = 'not_refilled';
    public const string NOT_FOUND = 'not_found';

    private const int RECHECK_AFTER_DAYS = 30;
    private const float SECONDS_BETWEEN_LOOKUPS = 3.7;

    public string $commandSignature = 'customers:verify-dic {customer? : customer id} {--organisation=sk : organisation slug} {--limit=900 : most lookups in one run}';

    public string $jobQueue = 'low-priority';

    public int $jobTries = 1;

    public static function isConfigured(): bool
    {
        return (bool)config('services.financna_sprava.api_key');
    }

    public static function appliesTo(Customer $customer): bool
    {
        return UpdateRetinaCustomer::needsPeppolDic($customer) && self::ico($customer) !== null;
    }

    public static function dispatchIfApplies(Customer $customer): void
    {
        if (self::isConfigured() && self::appliesTo($customer)) {
            static::dispatch($customer->id)->afterCommit();
        }
    }

    public static function ico(Customer $customer): ?string
    {
        $number = trim((string)$customer->identity_document_number);
        if (!preg_match('/^[\d\s]+$/', $number)) {
            return null;
        }
        $digits = preg_replace('/\D/', '', $number);

        return strlen($digits) >= 6 && strlen($digits) <= 8 ? str_pad($digits, 8, '0', STR_PAD_LEFT) : null;
    }

    /**
     * @return array{status: string, checked_at: string, ico: string, register_dic: string|null, register_name: string|null}|null
     */
    public function handle(int|Customer $customer): ?array
    {
        $customer = $customer instanceof Customer ? $customer : Customer::find($customer);
        if (!$customer || !self::isConfigured() || !self::appliesTo($customer)) {
            return null;
        }

        $ico        = self::ico($customer);
        $registered = $this->lookupByIco($ico);

        return DB::transaction(function () use ($customer, $ico, $registered) {
            $customer = Customer::lockForUpdate()->find($customer->id);
            if (!$customer || !self::appliesTo($customer) || self::ico($customer) !== $ico) {
                return null;
            }

            $dic         = UpdateRetinaCustomer::dicFor($customer);
            $firstCheck  = !Arr::has($customer->data ?? [], 'dic_verification');
            $canBeFilled = $registered !== null && BuildUblInvoice::isSlovakDic($registered['dic']) && !$this->isOwnDic($customer, $registered['dic']);

            $status = match (true) {
                $registered === null => self::NOT_FOUND,
                $dic !== null => $dic === $registered['dic'] ? self::MATCHED : self::MISMATCH,
                !$canBeFilled || !self::sameName($registered['name'], (string)($customer->company_name ?: $customer->name)) => self::NAME_DIFFERS,
                $firstCheck => self::FILLED,
                default => self::NOT_REFILLED,
            };

            if ($status === self::FILLED) {
                $customer->peppol_id = BuildUblInvoice::PEPPOL_SCHEME.':'.$registered['dic'];
            }

            $verification = [
                'status'        => $status,
                'checked_at'    => now()->toIso8601String(),
                'ico'           => $ico,
                'register_dic'  => $registered['dic'] ?? null,
                'register_name' => $registered['name'] ?? null,
            ];
            $customer->data = array_merge($customer->data ?? [], ['dic_verification' => $verification]);
            $customer->save();

            return $verification;
        });
    }

    /**
     * @return array{dic: string, ico: string, name: string}|null null when the register has no subject with this IČO
     */
    public function lookupByIco(string $ico): ?array
    {
        $response = Http::baseUrl(rtrim((string)config('services.financna_sprava.url'), '/'))
            ->withHeaders(['key' => (string)config('services.financna_sprava.api_key')])
            ->acceptJson()
            ->timeout(20)
            ->retry(1, 0, throw: false)
            ->get('/data/ds_dsrdp/search', ['page' => 1, 'column' => 'ico', 'search' => $ico]);

        if ($response->status() === 404) {
            return null;
        }
        if (!$response->successful()) {
            throw new RuntimeException("Finančná správa register lookup for IČO $ico failed with HTTP {$response->status()}");
        }

        $subject = collect($response->json('data') ?? [])->first(fn (array $row) => str_pad((string)Arr::get($row, 'ico'), 8, '0', STR_PAD_LEFT) === $ico);

        return $subject && Arr::get($subject, 'dic') ? [
            'dic'  => (string)$subject['dic'],
            'ico'  => (string)$subject['ico'],
            'name' => (string)Arr::get($subject, 'nazov_ds'),
        ] : null;
    }

    /**
     * Names compared without accents, case, punctuation and legal form, so "Obchod, s. r. o." is "OBCHOD s.r.o.".
     */
    public static function sameName(string $registered, string $ours): bool
    {
        $normalise = fn (string $name) => trim((string)preg_replace(
            ['/[^a-z0-9]+/', '/\b(spol|s r o|a s|v o s|k s|s e|o z)\b/'],
            [' ', ''],
            Str::lower(Str::ascii($name))
        ));

        $registered = preg_replace('/\s+/', '', $normalise($registered));
        $ours       = preg_replace('/\s+/', '', $normalise($ours));

        return $registered !== '' && $registered === $ours;
    }

    private function isOwnDic(Customer $customer, string $dic): bool
    {
        $taxNumber = $customer->shop?->taxNumber;

        return $taxNumber && (BuildUblInvoice::participantId($taxNumber->country_code.$taxNumber->number)['id'] ?? null) === $dic;
    }

    public function asCommand(Command $command): int
    {
        if (!self::isConfigured()) {
            $command->error('FINANCNA_SPRAVA_API_KEY is not set');

            return 1;
        }

        if ($command->argument('customer') !== null) {
            $verification = $this->handle((int)$command->argument('customer'));
            $command->line($verification ? (string)json_encode($verification, JSON_UNESCAPED_UNICODE) : 'not applicable');

            return 0;
        }

        $organisation = Organisation::where('slug', $command->option('organisation'))->firstOrFail();
        $limit        = (int)$command->option('limit');
        $counts       = [];
        $done         = 0;

        Customer::where('organisation_id', $organisation->id)
            ->whereNull('deleted_at')
            ->whereNotNull('identity_document_number')
            ->where(fn ($query) => $query->whereNull('data->dic_verification->checked_at')
                ->orWhere('data->dic_verification->checked_at', '<', now()->subDays(self::RECHECK_AFTER_DAYS)->toIso8601String()))
            ->lazyById(500)
            ->each(function (Customer $customer) use (&$counts, &$done, $limit, $command) {
                if ($done >= $limit) {
                    return false;
                }
                if (!self::appliesTo($customer)) {
                    return true;
                }
                try {
                    $status = $this->handle($customer->id)['status'] ?? 'skipped';
                } catch (RuntimeException $exception) {
                    $status = 'failed';
                    $command->warn("failed: $customer->reference ".$exception->getMessage());
                }
                $counts[$status] = ($counts[$status] ?? 0) + 1;
                $done++;
                if ($status === self::MISMATCH || $status === self::NAME_DIFFERS) {
                    $command->line("$status: $customer->reference ".($customer->company_name ?: $customer->name));
                }
                usleep((int)(self::SECONDS_BETWEEN_LOOKUPS * 1_000_000));

                return true;
            });

        $command->info("checked $done: ".json_encode($counts));

        return 0;
    }
}
