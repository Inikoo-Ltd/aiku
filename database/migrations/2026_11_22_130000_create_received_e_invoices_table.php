<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('received_e_invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups');
            $table->unsignedSmallInteger('organisation_id');
            $table->foreign('organisation_id')->references('id')->on('organisations');
            $table->string('provider');
            $table->string('provider_transaction_id');
            $table->string('transmission_id')->nullable();
            $table->string('sender_peppol_id')->nullable()->index();
            $table->string('sender_name')->nullable();
            $table->timestampTz('received_at')->nullable();
            $table->binary('xml')->nullable()->comment('the document exactly as received; never changed once written');
            $table->string('xml_sha256', 64)->nullable();
            $table->binary('pdf')->nullable()->comment('the access point rendering of the document; never changed once written');
            $table->string('pdf_sha256', 64)->nullable();
            $table->timestampTz('acknowledged_at')->nullable();
            $table->string('document_type')->nullable();
            $table->string('document_number')->nullable()->index();
            $table->date('issue_date')->nullable();
            $table->date('due_date')->nullable();
            $table->string('currency_code', 3)->nullable();
            $table->decimal('net_amount', 18, 2)->nullable();
            $table->decimal('tax_amount', 18, 2)->nullable();
            $table->decimal('total_amount', 18, 2)->nullable();
            $table->decimal('payable_amount', 18, 2)->nullable();
            $table->string('supplier_name')->nullable();
            $table->string('supplier_vat_number')->nullable();
            $table->string('supplier_tax_number')->nullable();
            $table->string('supplier_company_number')->nullable();
            $table->string('buyer_reference')->nullable();
            $table->string('order_reference')->nullable();
            $table->string('billing_reference')->nullable()->comment('on a credit note, the invoice it corrects');
            $table->text('parse_error')->nullable();
            $table->string('match_state')->default('unmatched')->index();
            $table->unsignedInteger('supplier_id')->nullable()->index();
            $table->foreign('supplier_id')->references('id')->on('suppliers');
            $table->unsignedInteger('purchase_order_id')->nullable()->index();
            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders');
            $table->unsignedInteger('stock_delivery_id')->nullable()->index();
            $table->foreign('stock_delivery_id')->references('id')->on('stock_deliveries');
            $table->jsonb('match_data')->default('{}');
            $table->timestampTz('matched_at')->nullable();
            $table->timestampsTz();
            $table->unique(['provider', 'provider_transaction_id']);
            $table->index(['organisation_id', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('received_e_invoices');
    }
};
