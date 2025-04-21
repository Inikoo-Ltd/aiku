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

        $accountingSupplierParty = new Party(
            new PartyIdentification($shop->id),
            new PartyName($shop->name),
            new PostalAddress(
                $shopAddress->address_line_1,
                $shopAddress->address_line_2,
                $shopAddress->locality,
                $shopAddress->postal_code,
                new Country($shopAddress->country->code, $shopAddress->country->name)
            )
        );

        $accountingSupplierParty->setContact(
            (new Contact())->setName($shop->contact_name)
                ->setTelephone($shop->phone)
                ->setElectronicMail($shop->email)
        );

        if ($shop->taxNumber) {
            $accountingSupplierParty->setPartyTaxScheme(
                new PartyTaxScheme($shop->taxNumber->number, $shop->taxNumber->getType($shopAddress->country)->value)
            );
        }

        $isDocInvoice = new InvoiceISDoc(
            $invoice->reference,
            $invoice->uuid,
            $invoice->date,
            $invoice->tax_amount > 0,
            $invoice->currency->code,
            new AccountingSupplierParty(
                $accountingSupplierParty
            )
        );


        // set customer party
        $customer        = $invoice->customer;
        $customerAddress = $customer->address;
        $customerParty   = new Party(
            new PartyIdentification($customer->id),
            new PartyName($customer->name),
            new PostalAddress(
                $customerAddress->address_line_1 ?? '',
                $customerAddress->address_line_2 ?? '',
                $customerAddress->locality ?? '',
                $customerAddress->postal_code ?? '',
                new Country($customerAddress->country->code, $customerAddress->country->name)
            )
        );

        $customerParty->setContact(
            (new Contact())->setName($customer->contact_name)
                ->setTelephone($customer->phone)
                ->setElectronicMail($customer->email)
        );

        if ($customer->taxNumber) {
            $customerParty->setPartyTaxScheme(
                new PartyTaxScheme($customer->taxNumber->number, $customer->taxNumber->getType($customerAddress->country)->value)
            );
        }

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
            $taxAmountPerUnitPrice = $transaction->historicAsset->price * $trTaxCategory->rate;
            $amountAfterTax        = $transaction->net_amount + $taxAmount;
            $unitPriceAfterTax     = $transaction->historicAsset->price + $taxAmountPerUnitPrice;

            $isDocInvoice->invoiceLines->add(
                (new InvoiceLine(
                    $transaction->id,
                    $transaction->net_amount,
                    $amountAfterTax,
                    $taxAmount,
                    $transaction->historicAsset->price,
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
                            $transaction->historicAsset->unit
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


}
