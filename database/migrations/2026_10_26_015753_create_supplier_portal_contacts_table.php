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
        Schema::create('supplier_portal_contacts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups');
            $table->unsignedInteger('supplier_id')->index();
            $table->foreign('supplier_id')->references('id')->on('suppliers');
            $table->string('email')->collation('und_ci')->index();
            $table->string('name')->nullable();
            $table->unsignedInteger('authorised_by_user_id')->nullable();
            $table->foreign('authorised_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->dateTimeTz('revoked_at')->nullable();
            $table->unsignedInteger('revoked_by_user_id')->nullable();
            $table->foreign('revoked_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->dateTimeTz('last_link_sent_at')->nullable();
            $table->unsignedInteger('number_links_sent')->default(0);
            $table->timestampsTz();
            $table->unique(['supplier_id', 'email']);
        });

        Schema::table('supplier_share_links', function (Blueprint $table) {
            $table->unsignedInteger('supplier_portal_contact_id')->nullable()->index()->comment('the contact who asked for it by email');
            $table->foreign('supplier_portal_contact_id')->references('id')->on('supplier_portal_contacts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('supplier_share_links', function (Blueprint $table) {
            $table->dropForeign(['supplier_portal_contact_id']);
            $table->dropColumn('supplier_portal_contact_id');
        });
        Schema::dropIfExists('supplier_portal_contacts');
    }
};
