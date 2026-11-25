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
 * Checks a Slovak business customer's DIČ against the Finančná správa registers: the income tax subjects (ds_dsrdp) by the
 * customer's IČO, or without an IČO the VAT payers (ds_dphs) by its Slovak VAT number, which also gives a missing IČO. The
 * result is kept in data.dic_verification. A customer without a DIČ gets the registered
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
        return UpdateRetinaCustomer::needsPeppolDic($customer) && self::lookupKey($customer) !== null;
    }

    /**
     * The IČO, searched in the register of income tax subjects; without one, a Slovak VAT number, searched in the list of
     * VAT payers (which also gives the IČO).
     *
     * @return array{0: string, 1: string}|null
     */
    public static function lookupKey(Customer $customer): ?array
    {
        if ($ico = self::ico($customer)) {
            return ['ico', $ico];
        }

        $taxNumber = $customer->taxNumber;
        if ($taxNumber?->country_code !== 'SK') {
            return null;
        }
        $number = preg_replace('/^SK/', '', strtoupper((string)preg_replace('/[^A-Za-z0-9]/', '', (string)$taxNumber->number)));

        return BuildUblInvoice::isSlovakDic($number) ? ['ic_dph', 'SK'.$number] : null;
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

        $key        = self::lookupKey($customer);
        $registered = $key[0] === 'ico' ? $this->lookupByIco($key[1]) : $this->lookupByVatNumber($key[1]);

        return DB::transaction(function () use ($customer, $key, $registered) {
            $customer = Customer::lockForUpdate()->find($customer->id);
            if (!$customer || !self::appliesTo($customer) || self::lookupKey($customer) !== $key) {
                return null;
            }

            $dic         = UpdateRetinaCustomer::dicFor($customer);
            $firstCheck  = !Arr::has($customer->data ?? [], 'dic_verification');
            $canBeFilled = $registered !== null && BuildUblInvoice::isSlovakDic($registered['dic']) && !$this->isOwnDic($customer, $registered['dic']);

            $status = match (true) {
                $registered === null => self::NOT_FOUND,
                $dic !== null => $dic === $registered['dic'] ? self::MATCHED : self::MISMATCH,
                !$canBeFilled || !self::isTheirs($customer, $registered) => self::NAME_DIFFERS,
                $firstCheck => self::FILLED,
                default => self::NOT_REFILLED,
            };

            if ($status === self::FILLED) {
                $customer->peppol_id = BuildUblInvoice::PEPPOL_SCHEME.':'.$registered['dic'];
            }
            if (in_array($status, [self::MATCHED, self::FILLED], true) && $key[0] === 'ic_dph' && preg_match('/^\d{6,8}$/', $registered['ico']) && blank($customer->identity_document_number)) {
                $customer->identity_document_number = str_pad($registered['ico'], 8, '0', STR_PAD_LEFT);
            }

            $verification = [
                'status'        => $status,
                'checked_at'    => now()->toIso8601String(),
                'ico'           => $key[0] === 'ico' ? $key[1] : ($registered['ico'] ?? null),
                'vat_number'    => $key[0] === 'ic_dph' ? $key[1] : null,
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
        $subject = collect($this->search('ds_dsrdp', 'ico', $ico))->first(fn (array $row) => str_pad((string)Arr::get($row, 'ico'), 8, '0', STR_PAD_LEFT) === $ico);

        return $subject && Arr::get($subject, 'dic') ? [
            'dic'  => (string)$subject['dic'],
            'ico'  => (string)$subject['ico'],
            'name' => (string)Arr::get($subject, 'nazov_ds'),
        ] : null;
    }

    /**
     * @return array{dic: string, ico: string, name: string}|null null when no VAT payer has this IČ DPH
     */
    public function lookupByVatNumber(string $vatNumber): ?array
    {
        $subject = collect($this->search('ds_dphs', 'ic_dph', $vatNumber))->first(fn (array $row) => strtoupper((string)Arr::get($row, 'ic_dph')) === $vatNumber);

        return $subject ? [
            'dic'  => substr($vatNumber, 2),
            'ico'  => (string)Arr::get($subject, 'ico'),
            'name' => (string)Arr::get($subject, 'nazov_ds'),
        ] : null;
    }

    /**
     * @return array<int, array<string, mixed>> the matching rows, empty when the register has none
     */
    private function search(string $list, string $column, string $value): array
    {
        $response = Http::baseUrl(rtrim((string)config('services.financna_sprava.url'), '/'))
            ->withHeaders(['key' => (string)config('services.financna_sprava.api_key')])
            ->acceptJson()
            ->timeout(20)
            ->retry(1, 0, throw: false)
            ->get("/data/$list/search", ['page' => 1, 'column' => $column, 'search' => $value]);

        if ($response->status() === 404) {
            return [];
        }
        if (!$response->successful()) {
            throw new RuntimeException("Finančná správa $list lookup for $column $value failed with HTTP {$response->status()}");
        }

        return $response->json('data') ?? [];
    }

    /**
     * The registered subject is the customer's when its name is the customer's company or contact name (a sole trader is
     * registered under the person's name), or when the DIČ the customer typed as its tax number is the registered one.
     *
     * @param array{dic: string, ico: string, name: string} $registered
     */
    public static function isTheirs(Customer $customer, array $registered): bool
    {
        $typedDic = preg_replace('/^SK/', '', strtoupper((string)preg_replace('/[^A-Za-z0-9]/', '', (string)$customer->taxNumber?->number)));
        if ($typedDic !== '' && $typedDic === $registered['dic']) {
            return true;
        }

        foreach ([$customer->company_name, $customer->contact_name, $customer->name] as $name) {
            if (trim((string)$name) !== '' && self::sameName($registered['name'], (string)$name)) {
                return true;
            }
        }

        return false;
    }

    private const string LEADING_LEGAL_FORMS = 'o z|oz|obcianske zdruzenie';
    private const string LEGAL_FORMS = 'spol s r o|spol sro|s r o|sro|spol|a s|as|v o s|k s|s e|n o|o z|oz|obcianske zdruzenie|registrovany socialny podnik|integracny podnik|druzstvo|limited|ltd';
    private const string TITLES = 'ing arch|mgr art|ph d|bc|mgr|ing|dr|mudr|mddr|mvdr|phdr|rndr|judr|paeddr|phd|csc|doc|prof|mba|jr|ml|st';
    private const array GENERATIONS = ['jr', 'ml', 'st'];
    private const string INSTITUTIONS = 'obec|mesto|mestske|mestska|skola|stredisko|urad|farnost|nadacia|zdruzenie|spolok|klub|centrum';

    /**
     * Names compared without accents, case, punctuation and the legal form at their start or end, so "Obchod, s. r. o."
     * is "OBCHOD sro" and "OZ Jelšovka" is "Občianske združenie Jelšovka". A registered person (a sole trader: 2 or 3
     * words, no legal form, no institution word) is also theirs when our name has no legal form and holds every word of
     * it, in any order and without academic titles, so "Galgóciová Daniela jr." and "Viera Bramušková - MUŠKA" are
     * "Daniela Galgóciová" and "Viera Bramušková"; "Ján Novák st." is never "Ján Novák ml.".
     */
    public static function sameName(string $registered, string $ours): bool
    {
        $registeredCompact = implode('', self::nameWords($registered, withoutLegalForm: true));
        if ($registeredCompact === '') {
            return false;
        }
        if ($registeredCompact === implode('', self::nameWords($ours, withoutLegalForm: true))) {
            return true;
        }

        return self::isPerson($registered) && !self::hasLegalForm($ours) && self::samePerson($registered, $ours);
    }

    private static function isPerson(string $registered): bool
    {
        $words = array_diff(self::nameWords($registered, withoutLegalForm: false), self::GENERATIONS);

        return count($words) >= 2 && count($words) <= 3
            && !self::hasLegalForm($registered)
            && !preg_match('/\d/', $registered)
            && !preg_match('/\b('.self::INSTITUTIONS.')\b/', implode(' ', $words));
    }

    private static function samePerson(string $registered, string $ours): bool
    {
        $registeredWords = array_values(array_diff(self::nameWords($registered, withoutLegalForm: false), self::GENERATIONS));
        $ourWords        = self::personWords($ours);
        $registeredGeneration = array_intersect(self::nameWords($registered, withoutLegalForm: false), self::GENERATIONS);
        $ourGeneration        = array_intersect(self::nameWords($ours, withoutLegalForm: false), self::GENERATIONS);
        if ($registeredGeneration !== [] && $ourGeneration !== [] && array_values($registeredGeneration) !== array_values($ourGeneration)) {
            return false;
        }

        return array_diff($registeredWords, $ourWords) === [];
    }

    private static function hasLegalForm(string $name): bool
    {
        return self::nameWords($name, withoutLegalForm: true) !== self::nameWords($name, withoutLegalForm: false);
    }

    /**
     * @return array<int, string>
     */
    private static function personWords(string $name): array
    {
        $plain = ' '.implode(' ', self::nameWords($name, withoutLegalForm: false)).' ';

        return self::words((string)preg_replace('/(?<= )('.self::TITLES.')(?= )/', ' ', $plain));
    }

    /**
     * @return array<int, string>
     */
    private static function nameWords(string $name, bool $withoutLegalForm): array
    {
        $plain = ' '.trim((string)preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii($name)))).' ';
        if ($withoutLegalForm) {
            $plain = (string)preg_replace('/^(?: (?:'.self::LEADING_LEGAL_FORMS.')(?= ))+|(?: (?:'.self::LEGAL_FORMS.'))+(?= $)/', '', $plain);
        }

        return self::words($plain);
    }

    /**
     * @return array<int, string>
     */
    private static function words(string $plain): array
    {
        return array_values(array_filter(explode(' ', $plain), fn (string $word) => $word !== ''));
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
            ->whereHas('address', fn ($query) => $query->where('country_code', 'SK'))
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
