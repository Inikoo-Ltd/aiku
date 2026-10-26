<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 26 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('supplier_submissions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups');
            $table->unsignedInteger('supplier_id')->index();
            $table->foreign('supplier_id')->references('id')->on('suppliers');
            $table->unsignedInteger('supplier_share_link_id')->nullable();
            $table->foreign('supplier_share_link_id')->references('id')->on('supplier_share_links')->nullOnDelete();
            $table->unsignedInteger('supplier_portal_contact_id')->nullable();
            $table->foreign('supplier_portal_contact_id')->references('id')->on('supplier_portal_contacts')->nullOnDelete();
            $table->unsignedInteger('supplier_product_id')->nullable()->index();
            $table->foreign('supplier_product_id')->references('id')->on('supplier_products')->nullOnDelete();
            $table->string('type')->index();
            $table->string('state')->index();
            $table->jsonb('data');
            $table->text('feedback')->nullable()->comment('what we tell the supplier');
            $table->date('expires_on')->nullable()->comment('compliance documents');
            $table->dateTimeTz('submitted_at')->nullable();
            $table->unsignedInteger('decided_by_user_id')->nullable();
            $table->foreign('decided_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->dateTimeTz('decided_at')->nullable();
            $table->dateTimeTz('applied_at')->nullable();
            $table->jsonb('applied')->nullable()->comment('what accepting it changed in aiku');
            $table->timestampsTz();
        });

        Schema::table('supplier_portal_contacts', function (Blueprint $table) {
            $table->string('phone')->nullable()->comment('checked by staff; for sending the link by WhatsApp');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_portal_contacts', function (Blueprint $table) {
            $table->dropColumn('phone');
        });
        Schema::dropIfExists('supplier_submissions');
    }
};
