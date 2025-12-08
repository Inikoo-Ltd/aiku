<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Fri, 04 Nov 2022 10:29:32 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class FetchReset
{
    use AsAction;
    use HasFetchReset;
    use WithAttributes;
    use WithAuroraOrganisationsArgument;

    public string $commandSignature = 'fetch:reset {organisations?*} {--d|db_suffix=}';

    private int $timeStart;

    private int $timeLastStep;

    public function asCommand(Command $command): int
    {
        $aikuIdField = 'aiku_id';
        $aikuGuestIdField = 'aiku_guest_id';

        $organisations = $this->getOrganisations($command);
        $exitCode = 0;

        foreach ($organisations as $organisation) {
            if ($databaseName = Arr::get($organisation->source, 'db_name')) {
                $command->line("🏃 org: $organisation->slug ");
                $this->setAuroraConnection($databaseName, $command->option('db_suffix'));

                DB::connection('aurora')->table('pika_fetch')->truncate();
                DB::connection('aurora')->table('pika_fetch_error')->truncate();

                $this->timeStart = microtime(true);
                $this->timeLastStep = microtime(true);

                DB::connection('aurora')->table('Account Dimension')
                    ->whereNotNull('aiku_id')
                    ->update(
                        [
                            'aiku_id' => null,
                        ]
                    );

                DB::connection('aurora')->table('History Dimension')
                    ->whereNotNull('aiku_notes_id')
                    ->update(
                        [
                            'aiku_notes_id' => null,
                        ]
                    );

                DB::connection('aurora')->table('History Dimension')
                    ->whereNotNull($aikuIdField)
                    ->update(
                        [
                            $aikuIdField => null,
                        ]
                    );

                DB::connection('aurora')->table('History Dimension')
                    ->whereNotNull('aiku_notes_id')
                    ->update(
                        [
                            'aiku_subscribe_event_id' => null,
                        ]
                    );

                DB::connection('aurora')->table('Barcode Dimension')
                    ->update(
                        [
                            $aikuIdField => null,
                        ]
                    );

                DB::connection('aurora')->table('Staff Dimension')
                    ->update(
                        [
                            $aikuIdField => null,
                            $aikuGuestIdField => null,
                        ]
                    );
                DB::connection('aurora')->table('Staff Deleted Dimension')
                    ->update(
                        [
                            $aikuIdField => null,
                            $aikuGuestIdField => null,
                        ]
                    );

                $command->line('✅ hr');
                DB::connection('aurora')->table('User Dimension')
                    ->update([$aikuIdField => null]);

                DB::connection('aurora')->table('User Deleted Dimension')
                    ->update([$aikuIdField => null]);

                $command->line('✅ sysadmins');

                DB::connection('aurora')->table('Store Dimension')
                    ->update([$aikuIdField => null]);

                DB::connection('aurora')->table('Shipper Dimension')
                    ->update([$aikuIdField => null]);

                DB::connection('aurora')->table('Product Dimension')
                    ->whereNotNull($aikuIdField)
                    ->update([$aikuIdField => null]);

                DB::connection('aurora')->table('Product History Dimension')
                    ->update([
                        $aikuIdField => null,
                    ]);

                DB::connection('aurora')->table('Category Dimension')
                    ->update(
                        [
                            'aiku_family_id' => null,
                            'aiku_department_id' => null,

                        ]
                    );

                $command->line('✅ shops');

                DB::connection('aurora')->table('Warehouse Dimension')
                    ->update([$aikuIdField => null]);
                DB::connection('aurora')->table('Warehouse Area Dimension')
                    ->update([$aikuIdField => null]);
                DB::connection('aurora')->table('Location Dimension')
                    ->update([$aikuIdField => null]);
                DB::connection('aurora')->table('Location Deleted Dimension')
                    ->update([$aikuIdField => null]);

                $command->line('✅ websites');
                DB::connection('aurora')->table('Website Dimension')
                    ->update([$aikuIdField => null]);
                DB::connection('aurora')->table('Page Store Dimension')
                    ->update([$aikuIdField => null]);

                DB::connection('aurora')->table('Deal Campaign Dimension')
                    ->update([$aikuIdField => null]);

                DB::connection('aurora')->table('Deal Dimension')
                    ->update([$aikuIdField => null]);

                DB::connection('aurora')->table('Deal Component Dimension')
                    ->update([$aikuIdField => null]);

                $command->line('✅ warehouses');

                DB::connection('aurora')->table('Agent Dimension')
                    ->update(
                        [
                            $aikuIdField => null,
                        ]
                    );

                DB::connection('aurora')->table('Supplier Dimension')
                    ->whereNotNull($aikuIdField)
                    ->update(
                        [
                            $aikuIdField => null,
                        ]
                    );
                DB::connection('aurora')->table('Supplier Deleted Dimension')
                    ->whereNotNull($aikuIdField)
                    ->update(
                        [
                            $aikuIdField => null,
                        ]
                    );

                DB::connection('aurora')->table('Supplier Part Dimension')
                    ->whereNotNull($aikuIdField)
                    ->update(
                        [
                            $aikuIdField => null,
                        ]
                    );
                DB::connection('aurora')->table('Supplier Part Deleted Dimension')
                    ->whereNotNull($aikuIdField)
                    ->update(
                        [
                            $aikuIdField => null,
                        ]
                    );

                DB::connection('aurora')->table('Supplier Part Historic Dimension')
                    ->whereNotNull('aiku_supplier_historic_product_id')
                    ->update(
                        [
                            'aiku_supplier_historic_product_id' => null,
                        ]
                    );

                $command->line('✅ agents/suppliers');

                DB::connection('aurora')->table('Attachment Bridge')
                    ->whereNotNull($aikuIdField)
                    ->update([$aikuIdField => null]);
                DB::connection('aurora')->table('Image Subject Bridge')
                    ->whereNotNull($aikuIdField)
                    ->update([$aikuIdField => null]);

                DB::connection('aurora')->table('Customer Dimension')
                    ->whereNotNull($aikuIdField)
                    ->update([$aikuIdField => null]);
                DB::connection('aurora')->table('Customer Deleted Dimension')
                    ->whereNotNull($aikuIdField)
                    ->update([$aikuIdField => null]);
                DB::connection('aurora')->table('Customer Client Dimension')
                    ->whereNotNull($aikuIdField)
                    ->update([$aikuIdField => null]);
                DB::connection('aurora')->table('Website User Dimension')
                    ->update([$aikuIdField => null]);

                DB::connection('aurora')->table('Credit Transaction Fact')
                    ->whereNotNull($aikuIdField)
                    ->update(
                        [
                            $aikuIdField => null,
                        ]
                    );

                DB::connection('aurora')->table('Customer Favourite Product Fact')->update([$aikuIdField => null]);
                DB::connection('aurora')->table('Back in Stock Reminder Fact')->update([$aikuIdField => null]);
                DB::connection('aurora')->table('Customer Portfolio Fact')
                    ->update([$aikuIdField => null]);

                DB::connection('aurora')->table('Shipping Zone Schema Dimension')
                    ->update([$aikuIdField => null]);
                DB::connection('aurora')->table('Shipping Zone Dimension')
                    ->update([$aikuIdField => null]);
                DB::connection('aurora')->table('Charge Dimension')
                    ->update([$aikuIdField => null]);

                DB::connection('aurora')->table('Fulfilment Rent Transaction Fact')
                    ->update([$aikuIdField => null]);

                DB::connection('aurora')->table('Fulfilment Asset Dimension')
                    ->update([$aikuIdField => null]);

                DB::connection('aurora')->table('Part Dimension')
                    ->whereNotNull($aikuIdField)
                    ->update([
                        //  'aiku_unit_id' => null,
                        $aikuIdField => null,
                    ]);

                DB::connection('aurora')->table('Part Deleted Dimension')
                    ->update([$aikuIdField => null]);

                $command->line("✅ inventory \t\t".$this->stepTime());

                DB::connection('aurora')->table('Purchase Order Dimension')
                    ->update([$aikuIdField => null]);
                DB::connection('aurora')->table('Supplier Delivery Dimension')
                    ->update([$aikuIdField => null]);

                DB::connection('aurora')->table('Purchase Order Transaction Fact')
                    ->update([
                        'aiku_po_id' => null,
                        'aiku_sd_id' => null,
                    ]);

                $command->line("✅ supplier products and PO \t".$this->stepTime());

                DB::connection('aurora')->table('Website User Dimension')
                    ->update([$aikuIdField => null]);
                DB::connection('aurora')->table('Prospect Dimension')
                    ->whereNotNull($aikuIdField)
                    ->update([$aikuIdField => null]);
                $command->line("✅ customers \t\t".$this->stepTime());

                DB::connection('aurora')->table('Payment Dimension')->update([$aikuIdField => null]);
                DB::connection('aurora')->table('Top Up Dimension')->update([$aikuIdField => null]);

                DB::connection('aurora')->table('Payment Account Dimension')->update([$aikuIdField => null]);
                DB::connection('aurora')->table('Payment Service Provider Dimension')->update([$aikuIdField => null]);
                $command->line("✅ payments \t\t".$this->stepTime());

                DB::connection('aurora')->table('Timesheet Dimension')
                    ->update([$aikuIdField => null]);
                DB::connection('aurora')->table('Timesheet Record Dimension')
                    ->update([$aikuIdField => null]);
                DB::connection('aurora')->table('Clocking Machine Dimension')
                    ->update([$aikuIdField => null]);
                $command->line("✅ HR \t\t\t".$this->stepTime());

                DB::connection('aurora')->table('Order Dimension')
                    ->whereNotNull($aikuIdField)
                    ->update([
                        $aikuIdField => null,
                    ]);

                DB::connection('aurora')->table('Order Dimension')
                    ->whereNotNull('aiku_all_id')
                    ->update([
                        'aiku_all_id' => null,
                    ]);

                DB::connection('aurora')->table('Order Transaction Fact')
                    ->whereNotNull($aikuIdField)
                    ->update(
                        [
                            $aikuIdField => null,
                        ]
                    );
                DB::connection('aurora')->table('Order Transaction Fact')
                    ->whereNotNull('aiku_basket_id')
                    ->update(
                        [
                            'aiku_basket_id' => null,
                        ]
                    );

                DB::connection('aurora')->table('Order Transaction Fact')
                    ->whereNotNull('aiku_invoice_id')
                    ->update(
                        [
                            'aiku_invoice_id' => null,
                        ]
                    );

                DB::connection('aurora')->table('Order Source Dimension')
                    ->whereNotNull('aiku_id')
                    ->update(
                        [
                            'aiku_id' => null,
                        ]
                    );

                DB::connection('aurora')->table('Order No Product Transaction Fact')->update(
                    [
                        $aikuIdField => null,
                        'aiku_basket_id' => null,
                        'aiku_invoice_id' => null,
                    ]
                );

                $command->line("✅ orders \t\t".$this->stepTime());

                DB::connection('aurora')->table('Delivery Note Dimension')
                    ->whereNotNull($aikuIdField)
                    ->update([$aikuIdField => null]);

                DB::connection('aurora')->table('Delivery Note Dimension')
                    ->whereNotNull('aiku_all_id')
                    ->update([
                        'aiku_all_id' => null,
                    ]);

                DB::connection('aurora')->table('Inventory Transaction Fact')
                    ->whereNotNull($aikuIdField)
                    ->update(
                        [
                            $aikuIdField => null,

                        ]
                    );

                DB::connection('aurora')->table('Inventory Transaction Fact')
                    ->whereNotNull('aiku_dn_item_id')
                    ->update(
                        [
                            'aiku_dn_item_id' => null,
                        ]
                    );

                DB::connection('aurora')->table('Inventory Transaction Fact')
                    ->whereNotNull('aiku_picking_id')
                    ->update(
                        [
                            'aiku_picking_id' => null,
                        ]
                    );

                $command->line("✅ delivery notes \t\t".$this->stepTime());

                DB::connection('aurora')
                    ->table('Invoice Dimension')
                    ->whereNotNull($aikuIdField)
                    ->update([$aikuIdField => null]);

                DB::connection('aurora')->table('Invoice Dimension')
                    ->whereNotNull('aiku_all_id')
                    ->update([
                        'aiku_all_id' => null,
                    ]);

                // DB::connection('aurora')->table('Invoice Deleted Dimension')->update([$aikuIdField => null]);

                $command->line("✅ invoices \t\t".$this->stepTime());

                DB::connection('aurora')->table('Email Campaign Type Dimension')
                    ->whereNotNull($aikuIdField)
                    ->update([$aikuIdField => null]);
                DB::connection('aurora')->table('Email Campaign Dimension')
                    ->update([
                        $aikuIdField => null,
                        'alt_aiku_id' => null,
                    ]);
                DB::connection('aurora')->table('Email Tracking Dimension')
                    ->whereNotNull($aikuIdField)
                    ->update([$aikuIdField => null]);
                DB::connection('aurora')->table('Email Tracking Event Dimension')
                    ->whereNotNull($aikuIdField)
                    ->update([$aikuIdField => null]);

                DB::connection('aurora')->table('Email Tracking Email Copy')
                    ->whereNotNull($aikuIdField)
                    ->update([$aikuIdField => null]);

                DB::connection('aurora')->table('Order Sent Email Bridge')
                    ->whereNotNull($aikuIdField)
                    ->update([$aikuIdField => null]);

                $command->line("✅ post rooms \t\t".$this->stepTime());
            }
        }

        return $exitCode;
    }
}
