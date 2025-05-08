<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 12:55:09 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\CRM\Customer\CustomerStateEnum;
use App\Enums\CRM\Customer\CustomerStatusEnum;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FetchAuroraCustomer extends FetchAurora
{
    protected function parseModel(): void
    {
        $shop = $this->parseShop($this->organisation->id.':'.$this->auroraModelData->{'Customer Store Key'});

        if ($shop->type == ShopTypeEnum::FULFILMENT) {
            return;
        }


        $this->parsedData['shop'] = $shop;

        $status = CustomerStatusEnum::APPROVED->value;
        $state  = CustomerStateEnum::ACTIVE->value;
        if ($this->auroraModelData->{'Customer Type by Activity'} == 'Rejected') {
            $status = CustomerStatusEnum::REJECTED->value;
        } elseif ($this->auroraModelData->{'Customer Type by Activity'} == 'ToApprove') {
            $state  = CustomerStateEnum::REGISTERED->value;
            $status = CustomerStatusEnum::PENDING_APPROVAL->value;
        } elseif ($this->auroraModelData->{'Customer Type by Activity'} == 'Losing') {
            $state = CustomerStateEnum::LOSING->value;
        } elseif ($this->auroraModelData->{'Customer Type by Activity'} == 'Lost') {
            $state = CustomerStateEnum::LOST->value;
        }

        if (!$shop->registration_needs_approval  &&  $status == CustomerStatusEnum::PENDING_APPROVAL->value) {
            $status = CustomerStatusEnum::APPROVED->value;
        }


        $billingAddress  = $this->parseAddress(prefix: 'Customer Invoice', auAddressData: $this->auroraModelData);
        $deliveryAddress = $this->parseAddress(prefix: 'Customer Delivery', auAddressData: $this->auroraModelData);

        if (Arr::get($billingAddress, 'country_id') == null) {
            $billingAddress['country_id'] = $this->parsedData['shop']->country_id;
        }
        if (Arr::get($deliveryAddress, 'country_id') == null) {
            $deliveryAddress['country_id'] = $this->parsedData['shop']->country_id;
        }

        $taxNumberFromAurora = $this->auroraModelData->{'Customer Tax Number'};
        if ($taxNumberFromAurora) {
            $taxNumberFromAurora = preg_replace("/[^a-zA-Z0-9\-]/", "", $this->sanitiseText($taxNumberFromAurora));
        }

        $taxNumber = $this->parseTaxNumber(
            number: $taxNumberFromAurora,
            countryID: $billingAddress['country_id'],
            rawData: (array)$this->auroraModelData
        );


        $contactName = $this->auroraModelData->{'Customer Main Contact Name'};
        $company     = $this->auroraModelData->{'Customer Company Name'};


        $contactName = $this->cleanName($contactName);

        $company = $this->cleanName($company);
        $company = $this->cleanCompanyName($company);


        if (!$company && !$contactName) {
            $contactName = $this->auroraModelData->{'Customer Name'};
            $contactName = $this->cleanName($contactName);
            $contactName = $this->cleanCompanyName($contactName);
            if (!$contactName) {
                $contactName = '***';
            }
        }

        $phone = $this->cleanPhone($this->auroraModelData->{'Customer Main Plain Mobile'});
        if ($phone == '') {
            $phone = $this->cleanPhone($this->auroraModelData->{'Customer Main Plain Telephone'});
        }

        if (is_numeric($contactName)) {
            $tmp = preg_replace('/[^0-9]/i', '', $contactName);
            $tmp = (string)preg_replace('/^0/', '', $tmp);

            if (strlen($contactName) > 6 and preg_match("/$tmp/", $phone)) {
                $contactName = '';
            }
            if ($contactName != '' and $company == '') {
                $company     = $contactName;
                $contactName = '';
            }
        }

        $internalNotes          = $this->auroraModelData->{'Customer Sticky Note'};
        $warehouseInternalNotes = $this->auroraModelData->{'Customer Order Sticky Note'};
        $warehousePublicNotes   = $this->auroraModelData->{'Customer Delivery Sticky Note'};


        $internalNotes          = $this->clearTextWithHtml($internalNotes);
        $warehouseInternalNotes = $this->clearTextWithHtml($warehouseInternalNotes);
        $warehousePublicNotes   = $this->clearTextWithHtml($warehousePublicNotes);

        $emailSubscriptions = [
            'is_subscribed_to_newsletter'        => $this->auroraModelData->{'Customer Send Newsletter'} == 'Yes',
            'is_subscribed_to_marketing'         => $this->auroraModelData->{'Customer Send Email Marketing'} == 'Yes',
            'is_subscribed_to_abandoned_cart'    => true,
            'is_subscribed_to_reorder_reminder'  => true,
            'is_subscribed_to_basket_low_stock'  => $this->auroraModelData->{'Customer Send Basket Emails'} == 'Yes',
            'is_subscribed_to_basket_reminder_1' => $this->auroraModelData->{'Customer Send Basket Emails'} == 'Yes',
            'is_subscribed_to_basket_reminder_2' => $this->auroraModelData->{'Customer Send Basket Emails'} == 'Yes',
            'is_subscribed_to_basket_reminder_3' => $this->auroraModelData->{'Customer Send Basket Emails'} == 'Yes',


        ];

        $isVip = $this->auroraModelData->{'Customer Level Type'} == 'VIP';

        $AsOrganisation = null;
        if ($this->auroraModelData->{'Customer Level Type'} == 'Partner') {
            if (in_array($this->auroraModelData->{'Customer Name'}, ['Ancient Wisdom Marketing Ltd', 'Ancient Wisdom', 'Ancient Wisdom Marketing Ltd.'])) {
                $AsOrganisation = Organisation::where('slug', 'aw')->first();
            } elseif ($this->auroraModelData->{'Customer Name'} == 'Ancient Wisdom s.r.o.') {
                $AsOrganisation = Organisation::where('slug', 'sk')->first();
            } elseif (in_array($this->auroraModelData->{'Customer Name'}, ['AW Artisan S.L', 'AW Artisan S. L', 'AW-REGALOS SL', 'AW Artisan S.L.'])) {
                $AsOrganisation = Organisation::where('slug', 'es')->first();
            } elseif (in_array($this->auroraModelData->{'Customer Name'}, ['AW Aromatics Ltd', 'AW Aromatics'])) {
                $AsOrganisation = Organisation::where('slug', 'aroma')->first();
            } elseif (in_array($this->auroraModelData->{'Customer Name'}, ['aw China', 'Yiwu Saikun Import And EXPORT CO., Ltd', 'Yiwu Saikun Import And Export CO., Ltd'])) {
                $AsOrganisation = Organisation::where('slug', 'china')->first();
            }

            if (in_array($this->auroraModelData->{'Customer Key'}, [
                10362,
                17032,
                392469
            ])) {
                $isVip = true;
            }
        }

        $asEmployeeID = null;


        $this->parsedData['customer'] =
            [
                'reference'           => sprintf('%05d', $this->auroraModelData->{'Customer Key'}),
                'state'               => $state,
                'status'              => $status,
                'source_id'           => $this->organisation->id.':'.$this->auroraModelData->{'Customer Key'},
                'created_at'          => $this->auroraModelData->{'Customer First Contacted Date'},
                'registered_at'       => $this->auroraModelData->{'Customer First Contacted Date'},
                'contact_address'     => $billingAddress,
                'tax_number'          => $taxNumber,
                'email_subscriptions' => $emailSubscriptions,
                'fetched_at'          => now(),
                'last_fetched_at'     => now(),
                'is_vip'              => $isVip,
                'as_organisation_id'  => $AsOrganisation?->id,
                'as_employee_id'      => $asEmployeeID
            ];


        if ($internalNotes != '') {
            $this->parsedData['customer']['internal_notes'] = $internalNotes;
        }

        if ($warehouseInternalNotes != '') {
            $this->parsedData['customer']['warehouse_internal_notes'] = $warehouseInternalNotes;
        }

        if ($warehousePublicNotes != '') {
            $this->parsedData['customer']['warehouse_public_notes'] = $warehousePublicNotes;
        }


        if ($contactName != '') {
            $this->parsedData['customer']['contact_name'] = $contactName;
        }

        if ($company != '') {
            $this->parsedData['customer']['company_name'] = $company;
        }

        $email = $this->auroraModelData->{'Customer Main Plain Email'};
        if ($email != '') {
            $this->parsedData['customer']['email'] = $email;
        }

        if ($phone != '') {
            $this->parsedData['customer']['phone'] = $phone;
        }

        $website = $this->cleanUrl($this->auroraModelData->{'Customer Website'});
        if ($website != '') {
            $this->parsedData['customer']['contact_website'] = $website;
        }

        $identityDocumentNumber = $this->auroraModelData->{'Customer Registration Number'};
        if ($identityDocumentNumber) {
            $identityDocumentNumber = $this->cleanCompanyNumber(Str::limit($identityDocumentNumber));
            if ($identityDocumentNumber != '' and $company != $identityDocumentNumber) {
                $this->parsedData['customer']['identity_document_number'] = $identityDocumentNumber;
            }
        }


        if ($billingAddress != $deliveryAddress) {
            $this->parsedData['customer']['delivery_address'] = $deliveryAddress;
        }
    }


    protected function fetchData($id): object|null
    {
        return DB::connection('aurora')
            ->table('Customer Dimension')
            ->where('Customer Key', $id)->first();
    }


    protected function cleanCompanyName($name): string
    {
        $name = str_replace('(Ebay/Amazon Account)', '', $name);
        $name = trim($name);

        if (preg_match('/-*\s*(None|Not yet started trading|undecided as of yet)\s*-*/', $name)) {
            $name = '';
        }

        if (in_array($name, ['none', '0n-line Amazon retail', '-', 'Unknown', '- None -', '- Not yet started trading -', '- Select -', '--Please Select--'])) {
            $name = '';
        }

        return $name;
    }

    protected function cleanName($name): string
    {
        if ($name === null) {
            return '';
        }

        $name = trim($name);

        if (preg_match('/^([:\-.+,*01?#_\/])+$/', $name)) {
            $name = '';
        }

        return $name;
    }


    protected function cleanCompanyNumber($string): string
    {
        $string = $this->cleanName($string);
        $string = $this->cleanCompanyName($string);

        $string = preg_replace('/^([:;])\s*/', '', $string);


        if (preg_match('/(No tengo|Don.?t have|Dont have|I don.t have|not Applicable|Not available|Not yet|Sheffield|independente em nome próprio|Sole Trader|carmen|carlos|En proceso|entrepreneur|unknown|test|to follow|Under construction)/i', $string)) {
            $string = '';
        }

        if (in_array($string, [
            'Zürich',
            'universes',
            'sra.',
            'Noch nicht vorhanden',
            'No hay número de registro',
            'Slovenská Republika',
            'slovensko',
            'Slovensko/Slovakia',
            'recargo de equivalencia',
            'nose',
            'No Selection',
            'No lo se',
            'Empresa en nombre individual',
            'new business',
            'En projet',
            '99999999999999999999999999999',
            'Bratislavský',
            'Česká Republika',
            'Miss',
            '65 avenue de la Chaumière',
            'autonomo',
            'Autónoma',
            'Autonomo',
            'Aún no tengo',
            'Slovensko',
            'autoentrepreneur',
            'Slovensko (SK)',
            'Wien',
            'N /a',
            'N.A.',
            'N/A Local Authority',
            'n/a partnershiop',
            'Na sole trader',
            'Não tenho',
            'Česká republika',
            'Sra.',
            'Sra',
            'Sra.',
            'Sra',
            'Sra.',
            'Sra',
            'Sra.',
            'Sra',
            '///',
            '1100 Tatra banka, a.s.',
            '21% IVA (NO RECARGO)',
            '5,2%',
            '5,20',
            'Barcelona',
            "Ali'ne baba et ses 4500 produits",
            'SK',
            'GB',
            'EL',
            'na',
            'NA',
            'n/a',
            'N/A',
            'N/a',
            'en cour',
            'en cours',
            'MT',
            '-SR',
            '000 000 000 000 00',
            'Slovenská republika',
            'En cours pas recu encore',
            'Slowakei',
            'Slovakia'
        ])) {
            $string = '';
        }

        if (strlen($string) < 4) {
            $string = '';
        }

        return $string;
    }


    protected function cleanUrl($url): string
    {
        $url = $this->cleanName($url);
        if ($url == 'www.') {
            $url = '';
        }

        return $url;
    }


    protected function cleanPhone($phone): string
    {
        $phone = $this->cleanName($phone);
        if (in_array($phone, [
            ')575€43347',
            '+xxx',
            'na',
            'n/a',
            'N/A',
            'N/a',
            '=!/&&$!%"§(=',
            'NotAccessible',
            'why',
            'Y',
            '\\',
            'Xxxxxx.',
            'Om'

        ])) {
            $phone = '';
        }

        $digitsOnly = preg_replace('/[^0-9]/i', '', $phone);
        if (strlen($digitsOnly) < 5) {
            $phone = '';
        }

        return $phone;
    }

}
