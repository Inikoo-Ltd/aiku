<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('supplier_portal_profiles', function (Blueprint $table) {
            $table->increments('id');
            $table->ulid()->unique()->comment('kept in the visitor cookie');
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups');
            $table->unsignedInteger('supplier_id')->index();
            $table->foreign('supplier_id')->references('id')->on('suppliers');
            $table->unsignedInteger('supplier_portal_contact_id')->nullable()->unique()->comment('the email that logs in by itself');
            $table->foreign('supplier_portal_contact_id')->references('id')->on('supplier_portal_contacts')->nullOnDelete();
            $table->unsignedInteger('supplier_share_link_id')->nullable()->index()->comment('the direct link it was first opened with');
            $table->foreign('supplier_share_link_id')->references('id')->on('supplier_share_links')->nullOnDelete();
            $table->string('name')->nullable();
            $table->string('phone', 64)->nullable();
            $table->unsignedSmallInteger('theme')->default(0);
            $table->unsignedInteger('image_id')->nullable();
            $table->foreign('image_id')->references('id')->on('media')->nullOnDelete();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_portal_profiles');
    }
};
