<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 14-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Accounting\Invoice;

use Adawolfa\ISDOC\Invoice as InvoiceISDoc;
use Adawolfa\ISDOC\Manager;
use Adawolfa\ISDOC\Schema\Invoice\AccountingCustomerParty;
use Adawolfa\ISDOC\Schema\Invoice\AccountingSupplierParty;
use Adawolfa\ISDOC\Schema\Invoice\ClassifiedTaxCategory;
use Adawolfa\ISDOC\Schema\Invoice\Contact;
use App\Actions\OrgAction;
use App\Models\Accounting\Invoice;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\Response;
use Adawolfa\ISDOC\Schema\Invoice\Country;
use Adawolfa\ISDOC\Schema\Invoice\Delivery;
use Adawolfa\ISDOC\Schema\Invoice\InvoiceLine;
use Adawolfa\ISDOC\Schema\Invoice\Item;
use Adawolfa\ISDOC\Schema\Invoice\Party;
use Adawolfa\ISDOC\Schema\Invoice\PartyIdentification;
use Adawolfa\ISDOC\Schema\Invoice\PartyName;
use Adawolfa\ISDOC\Schema\Invoice\PartyTaxScheme;
use Adawolfa\ISDOC\Schema\Invoice\PostalAddress;
use Adawolfa\ISDOC\Schema\Invoice\Quantity;
use Adawolfa\ISDOC\Schema\Invoice\TaxCategory;
use Adawolfa\ISDOC\Schema\Invoice\TaxSubTotal;
use Illuminate\Support\Str;

class ISDocInvoice extends OrgAction
{
    /**
     * @throws \Adawolfa\ISDOC\WriterException
     */
    public function handle(Invoice $invoice): string
    {
        $manager     = Manager::create();
        $shop        = $invoice->shop;
        $shopAddress = $shop->address;

        $accountingSupplierParty = $this->createParty(
            id: $shop->id,
            name: $shop->name,
            address: $shopAddress,
            contactName: $shop->contact_name,
            phone: $shop->phone,
            email: $shop->email,
            taxNumber: $shop->taxNumber
        );

        $isDocInvoice = new InvoiceISDoc(
            $invoice->reference,
            $invoice->uuid ?? Str::uuid(),
            $invoice->date,
            $invoice->tax_amount > 0,
            $invoice->currency->code,
            new AccountingSupplierParty(
                $accountingSupplierParty
            )
        );


        $customer       = $invoice->customer;
        $invoiceAddress = $invoice->address;

        $customerParty = $this->createParty(
            id: $customer->id,
            name: $customer->name,
            address: $invoiceAddress,
            contactName: $customer->contact_name,
            phone: $customer->phone,
            email: $customer->email,
            taxNumber: $customer->taxNumber
        );

        $isDocInvoice->setAccountingCustomerParty(
            new AccountingCustomerParty(
                $customerParty
            )
        );


        $isDocInvoice->taxPointDate = $invoice->tax_liability_at;

        $transactions = $invoice->invoiceTransactions;


        foreach ($transactions as $transaction) {
            $trTaxCategory = $transaction->taxCategory;

            $taxAmount             = $transaction->net_amount * $trTaxCategory->rate;
            $unitPrice             = $transaction->historicAsset?->price ?? $transaction->net_amount;
            $taxAmountPerUnitPrice = $unitPrice * $trTaxCategory->rate;
            $amountAfterTax        = $transaction->net_amount + $taxAmount;
            $unitPriceAfterTax     = $unitPrice + $taxAmountPerUnitPrice;

            $isDocInvoice->invoiceLines->add(
                (new InvoiceLine(
                    $transaction->id,
                    $transaction->net_amount,
                    $amountAfterTax,
                    $taxAmount,
                    $unitPrice,
                    $unitPriceAfterTax,
                    new ClassifiedTaxCategory(
                        $trTaxCategory->rate * 100,
                        ClassifiedTaxCategory::VAT_CALCULATION_METHOD_FROM_THE_BOTTOM,
                    )
                ))
                    ->setInvoicedQuantity(
                        (new Quantity())->setContent(
                            $transaction->quantity
                        )->setUnitCode(
                            $transaction->historicAsset?->unit
                        )
                    )
                    ->setItem(
                        (new Item())->setDescription($transaction->historicAsset?->name)
                    )
            );

            $isDocInvoice->taxTotal->add(
                new TaxSubTotal(
                    $transaction->net_amount,
                    $taxAmount,
                    $amountAfterTax,
                    '0',
                    '0',
                    '0',
                    '0',
                    '0',
                    '0',
                    new TaxCategory((string)($trTaxCategory->rate * 100)),
                )
            );
        }

        $deliveryAddress = $invoice->deliveryAddress;

        if ($deliveryAddress) {
            $isDocInvoice->setDelivery(
                new Delivery(
                    $this->createParty(
                        id: $deliveryAddress->id,
                        name: $customer->company_name ?? $customer->contact_name,
                        address: $deliveryAddress
                    )
                )
            );
        }


        // set tax total
        $isDocInvoice->legalMonetaryTotal
            ->setTaxExclusiveAmount(
                $invoice->net_amount
            );

        $isDocInvoice->taxTotal->setTaxAmount(
            $invoice->tax_amount
        );


        return $manager->writer->xml($isDocInvoice);
    }

    /**
     * @throws \Adawolfa\ISDOC\WriterException
     */
    public function asController(Organisation $organisation, Invoice $invoice, ActionRequest $request): Response
    {
        $this->initialisationFromShop($invoice->shop, $request);


        $xmlIsDocInvoice = $this->handle($invoice);

        $filename = $invoice->slug.'.isdoc';

        return response($xmlIsDocInvoice, 200)
            ->header('Content-Type', 'application/xml')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }

    private function createParty(
        int $id,
        string $name,
        mixed $address,
        ?string $contactName = null,
        ?string $phone = null,
        ?string $email = null,
        mixed $taxNumber = null
    ): Party {
        $party = new Party(
            new PartyIdentification((string)$id),
            new PartyName($name),
            new PostalAddress(
                $address->address_line_1 ?? '',
                $address->address_line_2 ?? '',
                $address->locality ?? '',
                $address->postal_code ?? '',
                new Country($address->country->code, $address->country->name)
            )
        );

        if ($contactName || $phone || $email) {
            $party->setContact(
                (new Contact())->setName($contactName)
                    ->setTelephone($phone)
                    ->setElectronicMail($email)
            );
        }

        if ($taxNumber) {
            $party->setPartyTaxScheme(
                new PartyTaxScheme($taxNumber->number, $taxNumber->getType($address->country)->value)
            );
        }

        return $party;
    }


}
