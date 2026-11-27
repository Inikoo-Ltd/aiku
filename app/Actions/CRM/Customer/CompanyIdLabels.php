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

    private static function isSlovakDic(string $value): bool
    {
        return BuildUblInvoice::isSlovakDic(preg_replace('/\D/', '', $value));
    }
}
