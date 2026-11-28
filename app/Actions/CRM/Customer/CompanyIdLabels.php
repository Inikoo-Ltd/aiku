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
     * Validation for the registration number field: a Belgian business must give a valid enterprise number.
     *
     * @param  callable(): ?string  $countryCode
     */
    public static function belgianEnterpriseNumberRule(callable $countryCode): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($countryCode) {
            if (is_string($value) && trim($value) !== '' && $countryCode() === 'BE' && self::belgianEnterpriseNumber($value) === null) {
                $fail(__('This is not a valid Belgian enterprise number. It has 10 digits, the same as the VAT number without BE.'));
            }
        };
    }

    /**
     * Stores a Belgian customer's enterprise number as 10 digits, taken from its Belgian VAT number when it gave none,
     * so the Peppol address (0208) is there before e-invoicing Belgium starts.
     */
    public static function fillBelgianEnterpriseNumber(Customer $customer): void
    {
        if ($customer->address?->country_code !== 'BE') {
            return;
        }

        $number = blank($customer->identity_document_number)
            ? ($customer->taxNumber?->country?->code === 'BE' ? self::belgianEnterpriseNumber($customer->taxNumber->number) : null)
            : self::belgianEnterpriseNumber($customer->identity_document_number);

        if ($number !== null && $number !== $customer->identity_document_number) {
            $customer->update(['identity_document_number' => $number]);
        }
    }

    private static function isSlovakDic(string $value): bool
    {
        return BuildUblInvoice::isSlovakDic(preg_replace('/\D/', '', $value));
    }
}
