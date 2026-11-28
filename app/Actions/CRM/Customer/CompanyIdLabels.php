<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Nov 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer;

use App\Actions\Accounting\EInvoice\BuildUblInvoice;
use App\Actions\Retina\SysAdmin\UpdateRetinaCustomer;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Helpers\Country;
use Closure;

/**
 * HELP-5000. The company numbers each country uses, labelled as the customer's country names them, so a Slovak customer
 * of any shop sees IČO, DIČ and IČ DPH. identity_document_number holds the registration number,
 * identity_document_number_alt the second national number where the country has one, tax_number the VAT number.
 * A Slovak DIČ lives in peppol_id (it routes e-invoices) and is copied onto invoices as the second number.
 * A label the shop set wins over the country's, except for Slovak customers, as the numbers already stored follow
 * the shop's label (e.g. the UK EORI/UKIMS fields).
 */
class CompanyIdLabels
{
    /**
     * @var array<string, array{0: string, 1: string|null, 2: string}> registration number, second number (null: none), VAT number
     */
    public const array COUNTRIES = [
        'SK' => ['IČO', 'DIČ', 'IČ DPH'],
        'CZ' => ['IČO', null, 'DIČ'],
        'PL' => ['REGON / KRS', 'NIP', 'NIP UE'],
        'HU' => ['Cégjegyzékszám', 'Adószám', 'Közösségi adószám'],
        'AT' => ['Firmenbuchnummer', null, 'UID-Nummer'],
        'DE' => ['Handelsregisternummer', null, 'USt-IdNr.'],
        'FR' => ['SIRET', null, 'N° TVA intracommunautaire'],
        'IT' => ['Codice fiscale', null, 'Partita IVA'],
        'NL' => ['KvK-nummer', null, 'btw-id'],
        'RO' => ['Nr. Reg. Com.', null, 'CUI / Cod TVA'],
        'ES' => ['NIF', null, 'NIF-IVA'],
        'BE' => ['Ondernemingsnummer / N° d\'entreprise', null, 'BTW / TVA'],
    ];

    /**
     * @return array{id: string, alt: string|null, vat: string}
     */
    public static function for(Shop $shop, ?string $countryCode): array
    {
        [$id, $alt, $vat] = self::COUNTRIES[$countryCode] ?? [__('Company reg. no.'), null, __('VAT number')];

        if ($countryCode !== 'SK') {
            $id  = data_get($shop->settings, 'customer.identity_document_number') ?? $id;
            $alt = data_get($shop->settings, 'customer.identity_document_number_alt') ?? $alt;
        }

        return ['id' => $id, 'alt' => $alt, 'vat' => $vat];
    }

    /**
     * Label of the customer's second number in its forms, null when it is not shown: a Slovak business customer's DIČ
     * has its own field, so an old free-text copy only shows when it differs, as does any number kept in a country
     * without a second one.
     */
    public static function customerAltLabel(Customer $customer): ?string
    {
        $labels = self::for($customer->shop, $customer->address?->country_code);
        $value = trim((string)$customer->identity_document_number_alt);

        if ($customer->address?->country_code === 'SK') {
            $dic = UpdateRetinaCustomer::dicFor($customer);

            if ($value === '') {
                return UpdateRetinaCustomer::showsDicField($customer) ? null : 'DIČ';
            }
            if ($dic !== null && preg_replace('/\D/', '', $value) === $dic) {
                return null;
            }

            return $dic === null && self::isSlovakDic($value) ? 'DIČ' : __('Other number');
        }

        return $labels['alt'] ?? ($value === '' ? null : __('Other number'));
    }

    /**
     * The second number on the customer's page and dashboard: a Slovak customer's DIČ shows once, as e-invoices use it.
     *
     * @return array{label: string, number: string}|null
     */
    public static function customerAltForDisplay(Customer $customer): ?array
    {
        if (blank($customer->identity_document_number_alt) && !str_starts_with((string)$customer->peppol_id, BuildUblInvoice::PEPPOL_SCHEME.':')) {
            return null;
        }

        $label = self::customerAltLabel($customer);
        if ($label !== null && $label !== '' && filled($customer->identity_document_number_alt)) {
            return ['label' => $label, 'number' => $customer->identity_document_number_alt];
        }

        $dic = $customer->address?->country_code === 'SK' ? UpdateRetinaCustomer::dicFor($customer) : null;

        return $dic ? ['label' => 'DIČ', 'number' => $dic] : null;
    }

    /**
     * The second number a new invoice carries: a Slovak customer's DIČ, as e-invoices use it, else what the customer holds.
     */
    public static function invoiceAlt(Customer $customer): ?string
    {
        if ($customer->address?->country_code === 'SK') {
            return UpdateRetinaCustomer::dicFor($customer) ?? $customer->identity_document_number_alt;
        }

        return $customer->identity_document_number_alt;
    }

    /**
     * Label of an invoice's second number: on a Slovak invoice "DIČ" only when the number is one.
     */
    public static function invoiceAltLabel(array $labels, ?string $countryCode, ?string $value): string
    {
        if ($countryCode === 'SK') {
            return self::isSlovakDic((string)$value) ? 'DIČ' : __('Other number');
        }

        return $labels['alt'] ?? __('Other number');
    }

    /**
     * INI-591. A Belgian enterprise number (KBO/BCE) as 10 digits, or null when it is not one: dots, spaces and a BE
     * prefix are dropped, an old 9-digit number gets its leading 0, and the last two digits must be 97 minus the first
     * eight mod 97. The VAT number is the same digits, so it gives the enterprise number too.
     */
    public static function belgianEnterpriseNumber(?string $value): ?string
    {
        $digits = preg_replace('/\D/', '', (string)$value);
        if (strlen($digits) === 9) {
            $digits = '0'.$digits;
        }
        if (!preg_match('/^[01]\d{9}$/', $digits)) {
            return null;
        }

        return 97 - (int)substr($digits, 0, 8) % 97 === (int)substr($digits, 8) ? $digits : null;
    }

    /**
     * INI-592. A Spanish NIF as 9 characters, or null when it is not one: spaces, dashes, an ES prefix and a NIF, NIE or
     * CIF typed before it are dropped, and a DNI missing its leading 0 gets it. A DNI (8 digits) or NIE (X, Y or Z and 7
     * digits) ends in the letter of the number mod 23; a company CIF (a letter and 7 digits) ends in its control digit or
     * the letter for it. K, L and M NIFs are given in either form, as sources disagree on which they use.
     */
    public static function spanishNif(?string $value): ?string
    {
        $nif = (string)preg_replace('/^(?:ES)?(?:NIF|NIE|CIF)?(?=.{9}$)/', '', strtoupper((string)preg_replace('/[^A-Za-z0-9]/', '', (string)$value)));
        if (preg_match('/^\d{7}[A-Z]$/', $nif)) {
            $nif = '0'.$nif;
        }

        if (preg_match('/^[XYZ\d]\d{7}[A-Z]$/', $nif)) {
            $number = (int)(strtr($nif[0], 'XYZ', '012').substr($nif, 1, 7));

            return 'TRWAGMYFPDXBNJZSQVHLCKE'[$number % 23] === $nif[8] ? $nif : null;
        }

        if (!preg_match('/^[ABCDEFGHJKLMNPQRSUVW]\d{7}[0-9A-J]$/', $nif)) {
            return null;
        }

        $sum = 0;
        foreach (str_split(substr($nif, 1, 7)) as $position => $digit) {
            $sum += $position % 2 === 0 ? array_sum(str_split((string)(2 * (int)$digit))) : (int)$digit;
        }
        $control = (10 - $sum % 10) % 10;
        $letter  = 'JABCDEFGHI'[$control];

        return match (true) {
            str_contains('KLM', $nif[0]) => $nif[8] === $letter || $nif[8] === 'TRWAGMYFPDXBNJZSQVHLCKE'[(int)substr($nif, 1, 7) % 23],
            str_contains('NPQRSW', $nif[0]) => $nif[8] === $letter,
            str_contains('ABEH', $nif[0]) => $nif[8] === (string)$control,
            default => in_array($nif[8], [$letter, (string)$control], true),
        } ? $nif : null;
    }

    /**
     * SQL true when the column holds a valid Spanish NIF, as spanishNif() reads it. A company CIF is checked by its shape
     * only.
     */
    public static function spanishNifSql(string $column): string
    {
        $nif    = "regexp_replace(regexp_replace(regexp_replace(upper(coalesce($column, '') collate \"C\"), '[^A-Z0-9]', '', 'g'), '^(ES)?(NIF|NIE|CIF)?(.{9})$', '\\3'), '^([0-9]{7}[A-Z])$', '0\\1')";
        $digits = "(translate(left($nif, 1), 'XYZ', '012') || substr($nif, 2))";

        return "(case when $digits ~ '^[0-9]{8}[A-Z]$'"
            ." then substr('TRWAGMYFPDXBNJZSQVHLCKE', left($digits, 8)::int % 23 + 1, 1) = right($digits, 1)"
            ." else $nif ~ '^[ABCDEFGHJKLMNPQRSUVW][0-9]{7}[0-9A-J]$' end)";
    }

    /**
     * The registration number in its national form, or null when it is not one; null too for a country we do not check.
     */
    public static function nationalNumber(?string $countryCode, ?string $value): ?string
    {
        return match ($countryCode) {
            'BE' => self::belgianEnterpriseNumber($value),
            'ES' => self::spanishNif($value),
            default => null,
        };
    }

    /**
     * Belgian and Spanish registration numbers are checked, unless the shop gave the field its own label (the UK shop
     * keeps EORI numbers there).
     */
    public static function checksNationalNumber(?Shop $shop, ?string $countryCode): bool
    {
        return in_array($countryCode, ['BE', 'ES'], true) && data_get($shop?->settings, 'customer.identity_document_number') === null;
    }

    /**
     * Validation for the registration number field: a Belgian business must give a valid enterprise number, a Spanish
     * customer a valid NIF. A number already held is let through unchanged, so an old one never blocks other changes.
     *
     * @param  callable(): ?string  $countryCode
     */
    public static function nationalNumberRule(callable $countryCode, ?Shop $shop, ?string $current = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($countryCode, $shop, $current) {
            if (!is_string($value) || trim($value) === '' || trim($value) === trim((string)$current)) {
                return;
            }

            $country = $countryCode();
            if (self::checksNationalNumber($shop, $country) && self::nationalNumber($country, $value) === null) {
                $fail($country === 'BE'
                    ? __('This is not a valid Belgian enterprise number. It has 10 digits, the same as the VAT number without BE.')
                    : __('This is not a valid Spanish NIF. It has 9 characters with its control letter or digit, e.g. 12345678Z or B12345674.'));
            }
        };
    }

    /**
     * Validation for the VAT number: a Spanish one is ES and the NIF, typed with or without ES. A number already held, or
     * one typed with another country's prefix, is let through.
     *
     * @param  callable(): ?string  $defaultCountryCode  the country when the VAT number gives none
     */
    public static function spanishVatNumberRule(callable $defaultCountryCode, ?string $current = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($defaultCountryCode, $current) {
            $number = trim((string)data_get($value, 'number'));
            if ($number === '' || $number === trim((string)$current)) {
                return;
            }

            $countryId = data_get($value, 'country_id');
            $country   = $countryId ? Country::find($countryId)?->code : (data_get($value, 'country_code') ?: $defaultCountryCode());
            $plain     = strtoupper((string)preg_replace('/[^A-Za-z0-9]/', '', $number));
            $isForeign = preg_match('/^[A-Z]{2}U?\d/', $plain) && !str_starts_with($plain, 'ES');
            if ($country === 'ES' && !$isForeign && self::spanishNif($number) === null) {
                $fail(__('This is not a valid Spanish NIF-IVA. It is ES and your NIF, e.g. ES12345678Z or ESB12345674.'));
            }
        };
    }

    /**
     * Stores a Belgian or Spanish customer's registration number in its national form, taken from its VAT number of
     * that country when it gave none (a Belgian VAT number is BE and the enterprise number, a Spanish one ES and the
     * NIF), so the number e-invoices need is there before e-invoicing starts.
     */
    public static function fillNationalNumber(Customer $customer): void
    {
        $country = $customer->address?->country_code;
        if (!self::checksNationalNumber($customer->shop, $country)) {
            return;
        }

        $number = blank($customer->identity_document_number)
            ? ($customer->taxNumber?->country?->code === $country ? self::nationalNumber($country, $customer->taxNumber->number) : null)
            : self::nationalNumber($country, $customer->identity_document_number);

        if ($number !== null && $number !== $customer->identity_document_number) {
            $customer->update(['identity_document_number' => $number]);
        }
    }

    private static function isSlovakDic(string $value): bool
    {
        return BuildUblInvoice::isSlovakDic(preg_replace('/\D/', '', $value));
    }
}
