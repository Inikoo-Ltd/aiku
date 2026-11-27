<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-27 10:00
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

use App\Enums\Procurement\SupplierOrderProposal\SupplierOrderProposalStateEnum;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('supplier_order_proposals', function (Blueprint $table) {
            $table->increments('id');
            $table = $this->groupOrgRelationship($table);

            $table->unsignedInteger('supplier_id')->index();
            $table->foreign('supplier_id')->references('id')->on('suppliers');
            $table->unsignedInteger('org_supplier_id')->index();
            $table->foreign('org_supplier_id')->references('id')->on('org_suppliers');
            $table->unsignedInteger('org_supplier_product_id')->index();
            $table->foreign('org_supplier_product_id')->references('id')->on('org_supplier_products');
            $table->unsignedInteger('supplier_product_id')->index();
            $table->foreign('supplier_product_id')->references('id')->on('supplier_products');

            $table->unsignedInteger('supplier_share_link_id')->nullable();
            $table->foreign('supplier_share_link_id')->references('id')->on('supplier_share_links')->nullOnDelete();
            $table->unsignedInteger('supplier_portal_contact_id')->nullable();
            $table->foreign('supplier_portal_contact_id')->references('id')->on('supplier_portal_contacts')->nullOnDelete();

            $table->string('state')->default(SupplierOrderProposalStateEnum::PENDING->value)->index();
            $table->string('bucket')->nullable()->comment('stock cover group when proposed: out or w1');
            $table->decimal('suggested_quantity_units', 16, 3);
            $table->decimal('quantity_units', 16, 3);
            $table->decimal('unit_cost', 16, 4)->comment('supplier price when proposed');
            $table->text('supplier_note')->nullable();

            $table->unsignedInteger('decided_by_user_id')->nullable();
            $table->foreign('decided_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->dateTimeTz('decided_at')->nullable();
            $table->text('reject_reason')->nullable()->comment('what we tell the supplier');
            $table->unsignedInteger('purchase_order_id')->nullable();
            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->nullOnDelete();
            $table->unsignedInteger('purchase_order_transaction_id')->nullable();
            $table->foreign('purchase_order_transaction_id')->references('id')->on('purchase_order_transactions')->nullOnDelete();

            $table->timestampsTz();
            $table->index(['organisation_id', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_order_proposals');
    }
};
