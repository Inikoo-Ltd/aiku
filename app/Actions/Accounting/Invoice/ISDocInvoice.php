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
use App\Actions\Traits\WithExportData;
use App\Models\Accounting\Invoice;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Symfony\Component\HttpFoundation\Response;
use Adawolfa\ISDOC\Schema\Invoice\Country;
use Adawolfa\ISDOC\Schema\Invoice\Details;
use Adawolfa\ISDOC\Schema\Invoice\InvoiceLine;
use Adawolfa\ISDOC\Schema\Invoice\Item;
use Adawolfa\ISDOC\Schema\Invoice\Party;
use Adawolfa\ISDOC\Schema\Invoice\PartyIdentification;
use Adawolfa\ISDOC\Schema\Invoice\PartyName;
use Adawolfa\ISDOC\Schema\Invoice\PartyTaxScheme;
use Adawolfa\ISDOC\Schema\Invoice\Payment;
use Adawolfa\ISDOC\Schema\Invoice\PaymentMeans;
use Adawolfa\ISDOC\Schema\Invoice\PostalAddress;
use Adawolfa\ISDOC\Schema\Invoice\Quantity;
use Adawolfa\ISDOC\Schema\Invoice\TaxCategory;
use Adawolfa\ISDOC\Schema\Invoice\TaxSubTotal;
use Ramsey\Uuid\Uuid;

class ISDocInvoice
{
    use AsAction;
    use WithAttributes;
    use WithExportData;
    use WithInvoicesExport;

    /**
     * @throws \Mpdf\MpdfException
     */
    public function handle(Invoice $invoice): Response
    {
        return $this->processDataExportPdf($invoice);
    }

    /**
     * @throws \Mpdf\MpdfException
     */
    public function asController(Organisation $organisation, Invoice $invoice): Response
    {
        return $this->handle($invoice);
    }

    public string $commandSignature = 'xxx';

    public function asCommand($command)
    {

        /** @var Invoice $invoiceData */
        $invoiceData = Invoice::find(950332);

        $manager = Manager::create();
        $shop = $invoiceData->shop;
        $shopAddress = $shop->address;

        // set supplier party
        $invoice = new InvoiceISDoc(
            "$invoiceData->reference",
            Uuid::uuid4()->toString(),
            $invoiceData->date,
            $invoiceData->tax_amount > 0,
            $invoiceData->currency->code,
            new AccountingSupplierParty(
                (new Party(
                    new PartyIdentification($shop->id),
                    new PartyName($shop->name),
                    new PostalAddress(
                        $shopAddress->address_line_1,
                        $shopAddress->address_line_2,
                        $shopAddress->locality,
                        $shopAddress->postal_code,
                        new Country($shopAddress->country->code, $shopAddress->country->name)
                    )
                ))->setContact(
                    (new Contact())->setName($shop->contact_name)
                        ->setTelephone($shop->phone)
                        ->setElectronicMail($shop->email)
                )
                ->setPartyTaxScheme(
                    new PartyTaxScheme($shop->taxNumber->number, $shop->taxNumber->getType($shop->country)->value)
                )
            )
        );




        // set customer party
        $customer = $invoiceData->customer;
        $customerAddress = $customer->address;
        $customerParty = new Party(
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

        $invoice->setAccountingCustomerParty(
            new AccountingCustomerParty(
                $customerParty
            )
        );


        $invoice->taxPointDate = $invoiceData->tax_liability_at;

        $transactions = $invoiceData->invoiceTransactions;


        foreach ($transactions as $tr) {
            $trTaxCategory = $tr->taxCategory;

            $taxAmount = $tr->net_amount * $trTaxCategory->rate;
            $taxAmountPerUnitprice = $tr->historicAsset->price * $trTaxCategory->rate;
            $amountAfterTax = $tr->net_amount + $taxAmount;
            $unitPriceAfterTax = $tr->historicAsset->price + $taxAmountPerUnitprice;

            $invoice->invoiceLines->add(
                (new InvoiceLine(
                    $tr->id,
                    $tr->net_amount,
                    $amountAfterTax,
                    $taxAmount,
                    $tr->historicAsset->price,
                    $unitPriceAfterTax,
                    new ClassifiedTaxCategory(
                        $trTaxCategory->rate * 100,
                        ClassifiedTaxCategory::VAT_CALCULATION_METHOD_FROM_THE_BOTTOM,
                    )
                ))
                ->setInvoicedQuantity(
                    (new Quantity())->setContent(
                        $tr->quantity
                    )->setUnitCode(
                        $tr->historicAsset->unit
                    )
                )
                ->setItem(
                    (new Item())->setDescription($tr->historicAsset?->name)
                )
            );

            $invoice->taxTotal->add(
                new TaxSubTotal(
                    $tr->net_amount,
                    $taxAmount,
                    $amountAfterTax,
                    '0',
                    '0',
                    '0',
                    '0',
                    '0',
                    '0',
                    new TaxCategory((string) ($trTaxCategory->rate * 100)),
                )
            );
        }



        // set tax total
        $invoice->legalMonetaryTotal
            ->setTaxExclusiveAmount(
                $invoiceData->net_amount
            );

        $invoice->taxTotal->setTaxAmount(
            $invoiceData->tax_amount
        );


        // if ($invoiceData->payments->count() > 0) {
        //     if ($invoice->paymentMeans === null) {
        //         $invoice->paymentMeans = new PaymentMeans(); // Initialize as a collection
        //     }

        //     foreach ($invoiceData->payments as $payment) {
        //         $invoice->paymentMeans->add(
        //             (new Payment(
        //                 $payment->amount,
        //                 Payment::PAYMENT_MEANS_CODE_CASH_PAYMENT,
        //             ))->setPaidAmount(
        //                 $payment->amount
        //             )->setDetails(
        //                 new Details(
        //                     $payment->paymentMethod->name,
        //                     $payment->paymentMethod->code,
        //                     $payment->paymentMethod->type,
        //                     $payment->paymentMethod->bankAccount
        //                 )
        //             )->setPaymentDate($payment->date)
        //         );
        //     }
        // }


        $manager->writer->file($invoice, 'filename.isdoc');
    }

}
